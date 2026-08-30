<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('provisioning previews an explicit account then changes only its role on commit', function (): void {
    $studentRole = Role::where('name', Role::STUDENT)->firstOrFail();
    $user = User::factory()->onboarded()->create(['role_id' => $studentRole->id]);
    $before = $user->fresh()->getAttributes();
    $options = ['user' => $user->id, '--confirm-email' => $user->email];
    $this->artisan('users:provision-super-admin', $options)->assertSuccessful();
    expect($user->fresh()->getAttributes())->toBe($before);

    $this->artisan('users:provision-super-admin', $options + ['--commit' => true])->assertSuccessful();
    $after = $user->fresh()->getAttributes();
    expect($user->fresh()->role->name)->toBe(Role::SUPER_ADMIN);
    unset($before['role_id'], $after['role_id'], $before['updated_at'], $after['updated_at']);
    expect($after)->toBe($before);
    $this->artisan('users:provision-super-admin', $options + ['--commit' => true])->assertFailed();
});

test('provisioning rejects missing or mismatched confirmation and never chooses another user', function (): void {
    $user = User::factory()->onboarded()->create(['role_id' => Role::where('name', Role::ADMIN)->firstOrFail()->id]);
    $before = $user->fresh()->getAttributes();
    $this->artisan('users:provision-super-admin', ['user' => $user->id, '--commit' => true])->assertFailed();
    $this->artisan('users:provision-super-admin', [
        'user' => $user->id, '--confirm-email' => 'wrong@example.test', '--commit' => true,
    ])->assertFailed();
    $this->artisan('users:provision-super-admin', [
        'user' => 999999, '--confirm-email' => $user->email, '--commit' => true,
    ])->assertFailed();
    expect($user->fresh()->getAttributes())->toBe($before);
});

test('provisioning refuses inactive deleted and legacy candidates', function (string $condition): void {
    $role = Role::firstOrCreate(['name' => $condition === 'legacy' ? 'manager' : Role::ADMIN], ['status' => true]);
    $user = User::factory()->onboarded()->create(['role_id' => $role->id]);
    if ($condition === 'inactive') {
        $user->update(['status' => 'inactive']);
    }
    if ($condition === 'deleted') {
        $user->delete();
    }
    $this->artisan('users:provision-super-admin', [
        'user' => $user->id, '--confirm-email' => $user->email, '--commit' => true,
    ])->assertFailed();
    expect(User::withTrashed()->findOrFail($user->id)->role_id)->toBe($role->id);
})->with(['inactive', 'deleted', 'legacy']);

test('an inactive or deleted super admin assignment still prevents bootstrap reuse', function (string $condition): void {
    $existing = User::factory()->create([
        'role_id' => Role::where('name', Role::SUPER_ADMIN)->firstOrFail()->id,
        'status' => 'inactive',
    ]);
    if ($condition === 'deleted') {
        $existing->delete();
    }
    $candidate = User::factory()->onboarded()->create(['role_id' => Role::where('name', Role::ADMIN)->firstOrFail()->id]);
    $this->artisan('users:provision-super-admin', [
        'user' => $candidate->id, '--confirm-email' => $candidate->email, '--commit' => true,
    ])->assertFailed();
    expect($candidate->fresh()->role->name)->toBe(Role::ADMIN);
})->with(['inactive', 'deleted']);

test('fixed role seeding is idempotent preserves legacy data and creates no credentialed accounts', function (): void {
    $legacy = Role::create(['name' => 'Cashier', 'status' => false]);
    $user = User::factory()->create(['role_id' => $legacy->id]);
    $legacy->delete();
    $before = $user->fresh()->getAttributes();
    $roles = Role::withTrashed()->count();
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);
    expect(Role::withTrashed()->count())->toBe($roles);
    expect(User::count())->toBe(1);
    expect($user->fresh()->getAttributes())->toBe($before);
    expect(Role::withTrashed()->findOrFail($legacy->id)->trashed())->toBeTrue();
});

test('fixed role migration is idempotent and rollback never removes assigned or legacy roles', function (): void {
    $legacy = Role::create(['name' => 'manager', 'status' => false]);
    $user = User::factory()->create(['role_id' => $legacy->id]);
    $rolesBefore = Role::withTrashed()->get()->toArray();
    $userBefore = $user->fresh()->getAttributes();
    $migration = require database_path('migrations/2026_08_30_214622_ensure_fixed_application_roles.php');
    $migration->up();
    $migration->down();
    expect(Role::withTrashed()->get()->toArray())->toBe($rolesBefore);
    expect($user->fresh()->getAttributes())->toBe($userBefore);
});
