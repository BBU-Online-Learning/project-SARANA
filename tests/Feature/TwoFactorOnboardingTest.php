<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

test('the default user factory still represents incomplete onboarding', function () {
    $user = User::factory()->create()->refresh();

    expect($user->google2fa_enabled)->toBeFalse();
    expect($user->google2fa_secret)->toBeNull();
    expect((bool) $user->must_change_password)->toBeTrue();
});

test('the onboarded factory state creates an active user with a usable two factor secret', function () {
    $user = User::factory()->onboarded()->create()->refresh();
    $google2fa = new Google2FA;

    expect($user->status)->toBe('active');
    expect($user->google2fa_enabled)->toBeTrue();
    expect((bool) $user->must_change_password)->toBeFalse();
    expect($google2fa->verifyKey(
        $user->google2fa_secret,
        $google2fa->getCurrentOtp($user->google2fa_secret)
    ))->toBeTrue();
});

test('guests are redirected to login before accessing protected pages', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
    $this->assertGuest();
})->with(['home', 'classes.index', 'chat.index', 'users.index', '2fa.setup']);

test('users without two factor setup cannot enter protected pages', function (string $roleName, string $routeName) {
    $role = Role::create(['name' => $roleName, 'status' => true]);
    $user = User::factory()->create([
        'role_id' => $role->id,
        'must_change_password' => false,
    ]);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertRedirect(route('2fa.setup'));

    $this->assertAuthenticatedAs($user);
})->with([
    'admin dashboard' => ['admin', 'home'],
    'admin users' => ['admin', 'users.index'],
    'admin classes' => ['admin', 'classes.index'],
    'admin chat' => ['admin', 'chat.index'],
    'teacher dashboard' => ['teacher', 'home'],
    'teacher classes' => ['teacher', 'classes.index'],
    'teacher chat' => ['teacher', 'chat.index'],
    'student dashboard' => ['student', 'home'],
    'student classes' => ['student', 'classes.index'],
    'student chat' => ['student', 'chat.index'],
]);

test('a teacher without two factor setup cannot create a class', function () {
    $role = Role::create(['name' => 'teacher', 'status' => true]);
    $teacher = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($teacher)
        ->post(route('classes.store'), ['name' => 'Blocked Class'])
        ->assertRedirect(route('2fa.setup'));

    $this->assertDatabaseCount('school_classes', 0);
});

test('an admin without two factor setup cannot generate a user secret', function () {
    $role = Role::create(['name' => 'admin', 'status' => true]);
    $admin = User::factory()->create(['role_id' => $role->id]);
    $targetUser = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('users.two-factor.generate', $targetUser))
        ->assertRedirect(route('2fa.setup'))
        ->assertSessionMissing("twofactor_setup_secret_{$targetUser->id}");

    expect($targetUser->refresh()->google2fa_secret)->toBeNull();
    expect($targetUser->google2fa_enabled)->toBeFalse();
});

test('password login sends a user without two factor setup to onboarding', function () {
    $user = User::factory()->create();

    $this->post(route('login.submit'), [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertRedirect(route('2fa.setup'))
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('an incomplete user can open setup without persisting the pending secret', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('2fa.setup'))
        ->assertOk()
        ->assertViewIs('auth.setup-2fa')
        ->assertSessionHas('pending_2fa_secret')
        ->assertViewHas('secret', fn ($secret): bool => $secret === \Illuminate\Support\Facades\Crypt::decryptString(session('pending_2fa_secret')));

    expect(session('pending_2fa_secret'))->toBeString()->not->toBeEmpty();
    expect($user->refresh()->google2fa_secret)->toBeNull();
    expect($user->google2fa_enabled)->toBeFalse();
});

test('confirmed setup persists the secret and redirects to the next onboarding step', function (bool $mustChangePassword) {
    $user = User::factory()->create(['must_change_password' => $mustChangePassword]);
    $google2fa = new Google2FA;
    $secret = $this->actingAs($user)->get(route('2fa.setup'))->viewData('secret');

    $this->actingAs($user)
        ->from(route('2fa.setup'))
        ->post(route('2fa.setup.submit'), [
            'code' => $google2fa->getCurrentOtp($secret),
        ])
        ->assertRedirect($mustChangePassword ? url('/change-password') : route('home'))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('pending_2fa_secret');

    $user->refresh();
    expect($user->google2fa_enabled)->toBeTrue();
    expect($user->google2fa_secret)->toBe($secret);
})->with(['password change required' => [true], 'password already changed' => [false]]);

test('setup rejects a secret that does not belong to the pending session', function () {
    $user = User::factory()->create();
    $google2fa = new Google2FA;
    $pendingSecret = $google2fa->generateSecretKey();
    $differentSecret = $google2fa->generateSecretKey();

    $this->actingAs($user)
        ->withSession(['pending_2fa_secret' => $pendingSecret])
        ->from(route('2fa.setup'))
        ->post(route('2fa.setup.submit'), [
            'secret' => $differentSecret,
            'code' => $google2fa->getCurrentOtp($differentSecret),
        ])
        ->assertRedirect(route('2fa.setup'))
        ->assertSessionHasErrors('secret')
        ->assertSessionHas('pending_2fa_secret', $pendingSecret);

    expect($user->refresh()->google2fa_enabled)->toBeFalse();
    expect($user->google2fa_secret)->toBeNull();
});

test('an incomplete user can log out without finishing setup', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['pending_2fa_secret' => 'pending-test-secret'])
        ->post(route('logout'))
        ->assertRedirect('/')
        ->assertSessionMissing('pending_2fa_secret');

    $this->assertGuest();
});
