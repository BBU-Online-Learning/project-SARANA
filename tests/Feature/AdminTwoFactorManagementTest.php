<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('direct admin QR generation confirmation and disable are retired without changing the target secret', function (): void {
    $admin = User::factory()->onboarded()->create(['role_id' => Role::where('name', Role::ADMIN)->firstOrFail()->id]);
    $target = User::factory()->onboarded()->create(['role_id' => Role::where('name', Role::STUDENT)->firstOrFail()->id]);
    $before = $target->fresh()->getAttributes();
    $this->actingAs($admin);
    foreach (['generate', 'enable', 'disable'] as $operation) {
        $this->postJson(route('users.two-factor.'.$operation, $target), ['code' => '123456'])->assertStatus(410);
    }
    expect($target->fresh()->getAttributes())->toBe($before);
});

test('the admin recovery form never displays a target authenticator secret or QR', function (): void {
    $admin = User::factory()->onboarded()->create(['role_id' => Role::where('name', Role::ADMIN)->firstOrFail()->id]);
    $target = User::factory()->onboarded()->create(['role_id' => Role::where('name', Role::STUDENT)->firstOrFail()->id]);
    $this->actingAs($admin)->withSession(["twofactor_setup_secret_{$target->id}" => $target->google2fa_secret])
        ->get(route('users.edit', $target))->assertOk()->assertSee('Send verified recovery link')->assertDontSee($target->google2fa_secret);
});
