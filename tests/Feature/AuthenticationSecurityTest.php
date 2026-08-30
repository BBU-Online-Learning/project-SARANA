<?php

use App\Models\User;
use App\Services\AuthSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('password login requires an expiring OTP challenge before authentication', function (): void {
    $user = securityTestUser();
    $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('2fa.challenge'));
    $this->assertGuest();
    expect(session('pending_2fa_expires_at'))->toBeGreaterThan(now()->timestamp);
    $this->post(route('2fa.challenge.submit'), ['code' => securityTestOtp($user)])
        ->assertRedirect(route('chat.index'))->assertSessionMissing('pending_2fa_user_id');
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->two_factor_last_used_step)->toBe(intdiv(now()->timestamp, 30));
});

test('inactive and suspended accounts cannot authenticate or keep using existing sessions', function (string $status): void {
    $user = securityTestUser(attributes: ['status' => $status]);
    $this->from(route('login'))->post(route('login.submit'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->actingAs($user)->get(route('classes.index'))->assertForbidden();
    $this->assertGuest();
})->with(['inactive', 'suspended']);

test('a suspended pending challenge cannot be completed', function (): void {
    $user = securityTestUser();
    $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'password']);
    $user->update(['status' => 'suspended']);
    $this->post(route('2fa.challenge.submit'), ['code' => securityTestOtp($user)])->assertRedirect(route('login'));
    $this->assertGuest();
});

test('expired and password-revoked challenges cannot authenticate', function (string $condition): void {
    $user = securityTestUser();
    $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'password']);
    if ($condition === 'expired') {
        $this->travel(AuthSecurityService::CHALLENGE_SECONDS + 1)->seconds();
    } else {
        $user->increment('auth_version');
    }
    $this->post(route('2fa.challenge.submit'), ['code' => securityTestOtp($user)])
        ->assertRedirect(route('login'))->assertSessionMissing('pending_2fa_user_id');
    $this->assertGuest();
})->with(['expired', 'revoked']);

test('an OTP cannot be replayed in another login challenge', function (): void {
    $user = securityTestUser();
    $credentials = ['email' => $user->email, 'password' => 'password'];
    $code = securityTestOtp($user);
    $this->post(route('login.submit'), $credentials);
    $this->post(route('2fa.challenge.submit'), ['code' => $code])->assertRedirect(route('chat.index'));
    $this->post(route('logout'));
    $this->post(route('login.submit'), $credentials);
    $this->postJson(route('2fa.challenge.submit'), ['code' => $code])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->assertGuest();
    $this->travel(31)->seconds();
    $this->post(route('2fa.challenge.submit'), ['code' => securityTestOtp($user)])->assertRedirect(route('chat.index'));
});

test('login attempts are limited without flashing credentials', function (): void {
    $user = securityTestUser();
    for ($i = 0; $i < 5; $i++) {
        $this->from(route('login'))->post(route('login.submit'), ['email' => $user->email, 'password' => 'private-wrong-password'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        expect(session('_old_input.password'))->toBeNull();
    }
    $this->postJson(route('login.submit'), ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
    $this->assertGuest();
});

test('OTP attempt limits survive restarting the password challenge', function (): void {
    $user = securityTestUser();
    $credentials = ['email' => $user->email, 'password' => 'password'];
    $this->post(route('login.submit'), $credentials);
    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('2fa.challenge.submit'), ['code' => 'invalid'])->assertUnprocessable();
    }
    $this->post(route('login.submit'), $credentials)->assertRedirect(route('2fa.challenge'));
    $this->postJson(route('2fa.challenge.submit'), ['code' => securityTestOtp($user)])->assertStatus(429);
    $this->assertGuest();
});

test('password changes are mandatory across protected pages and broadcast authorization', function (string $path, string $method): void {
    $user = securityTestUser('super_admin', ['must_change_password' => true]);
    $this->actingAs($user)->call($method, $path)->assertRedirect(route('password.change'));
})->with([['/home', 'GET'], ['/classes', 'GET'], ['/chat', 'GET'], ['/users', 'GET'], ['/broadcasting/auth', 'POST'], ['/disable-2fa', 'POST']]);

test('changing a password requires the current password and a fresh OTP', function (): void {
    $user = securityTestUser(attributes: ['must_change_password' => true]);
    $payload = ['password' => 'A-new-private-password-42', 'password_confirmation' => 'A-new-private-password-42'];
    $this->actingAs($user);
    $this->postJson(route('password.change.submit'), $payload + ['current_password' => 'wrong', 'code' => securityTestOtp($user)])
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->postJson(route('password.change.submit'), $payload + ['current_password' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->post(route('password.change.submit'), $payload + ['current_password' => 'password', 'code' => securityTestOtp($user)])
        ->assertRedirect(route('chat.index'));
    expect(Hash::check($payload['password'], $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->must_change_password)->toBeFalse();
    expect($user->fresh()->auth_version)->toBe(1);
    $this->get(route('classes.index'))->assertOk();

    $this->withSession(['auth_version' => 0])->actingAs($user->fresh())->get(route('classes.index'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('two factor removal needs password plus OTP and forces new onboarding', function (): void {
    $user = securityTestUser();
    $this->actingAs($user)->postJson(route('2fa.disable'), [])->assertUnprocessable();
    $this->postJson(route('2fa.disable'), ['current_password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->post(route('2fa.disable'), ['current_password' => 'password', 'code' => securityTestOtp($user)])
        ->assertRedirect(route('2fa.setup'));
    expect($user->fresh()->google2fa_enabled)->toBeFalse();
    expect($user->fresh()->google2fa_secret)->toBeNull();
    $this->assertAuthenticatedAs($user);
    $this->get(route('chat.index'))->assertRedirect(route('2fa.setup'));
});

test('setup secrets are encrypted in the session and expire', function (): void {
    $user = User::factory()->create();
    $secret = $this->actingAs($user)->get(route('2fa.setup'))->assertOk()->viewData('secret');
    expect(session('pending_2fa_secret'))->not->toBe($secret);
    expect(Crypt::decryptString(session('pending_2fa_secret')))->toBe($secret);
    $this->travel(AuthSecurityService::SETUP_SECONDS + 1)->seconds();
    $this->postJson(route('2fa.setup.submit'), ['code' => securityTestOtp($secret)])->assertUnprocessable();
    expect($user->fresh()->google2fa_enabled)->toBeFalse();
});

test('an enabled authenticator cannot be overwritten by posting to the setup endpoint', function (): void {
    $user = securityTestUser();
    $otherSecret = (new \PragmaRX\Google2FA\Google2FA)->generateSecretKey();
    $this->actingAs($user)->withSession([
        'pending_2fa_secret' => Crypt::encryptString($otherSecret),
        'pending_2fa_setup_user_id' => $user->id,
        'pending_2fa_setup_expires_at' => now()->timestamp + 600,
    ])->postJson(route('2fa.setup.submit'), ['code' => securityTestOtp($otherSecret)])->assertForbidden();
    expect($user->fresh()->google2fa_secret)->toBe($user->google2fa_secret);
});

test('malformed login identifiers are validation errors rather than server errors', function (): void {
    $this->postJson(route('login.submit'), ['email' => ['unexpected'], 'password' => 'password'])->assertUnprocessable();
});

test('a new account completes setup then verifies a fresh code to change its mandatory password', function (): void {
    $user = User::factory()->create(['role_id' => \App\Models\Role::where('name', 'student')->firstOrFail()->id]);
    $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('2fa.setup'));
    $secret = $this->get(route('2fa.setup'))->assertHeader('Referrer-Policy', 'no-referrer')->viewData('secret');
    $code = securityTestOtp($secret);
    $this->post(route('2fa.setup.submit'), ['code' => $code])->assertRedirect(route('password.change'));
    $this->get(route('classes.index'))->assertRedirect(route('password.change'));
    $payload = ['current_password' => 'password', 'password' => 'My-new-onboarding-password', 'password_confirmation' => 'My-new-onboarding-password'];
    $this->postJson(route('password.change.submit'), $payload + ['code' => $code])->assertUnprocessable();
    $this->travel(31)->seconds();
    $this->post(route('password.change.submit'), $payload + ['code' => securityTestOtp($secret)])->assertRedirect(route('chat.index'));
    $this->get(route('classes.index'))->assertOk();
});
