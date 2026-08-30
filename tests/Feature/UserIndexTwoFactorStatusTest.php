<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the user index shows two factor status badges', function () {
    $role = Role::create([
        'name' => 'admin',
        'description' => 'Admin test role',
        'status' => true,
    ]);

    $admin = User::factory()->onboarded()->create([
        'role_id' => $role->id,
        'email' => 'admin@example.com',
    ]);

    $disabledUser = User::factory()->create([
        'role_id' => Role::where('name', Role::STUDENT)->firstOrFail()->id,
        'email' => 'disabled@example.com',
        'google2fa_enabled' => false,
    ]);

    $enabledUser = User::factory()->onboarded()->create([
        'role_id' => Role::where('name', Role::STUDENT)->firstOrFail()->id,
        'email' => 'enabled@example.com',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('users.index'));

    $response->assertOk();
    $response->assertSee('Enabled');
    $response->assertSee('Disabled');
});
