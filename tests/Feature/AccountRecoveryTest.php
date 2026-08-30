<?php

use App\Models\Role;
use App\Services\AccountRecoveryService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    config(['mail.default' => 'array']);
});

function recoveryTestPayload($user, string $token): array
{
    return ['email' => $user->email, 'token' => $token, 'password' => 'Recovered-private-password-42', 'password_confirmation' => 'Recovered-private-password-42'];
}

test('email reset uses the configured application URL and preserves an existing authenticator', function (): void {
    config(['app.url' => 'https://institution.example']);
    $user = securityTestUser();
    $secret = $user->google2fa_secret;
    $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.request'))->assertSessionHas('status', AccountRecoveryService::LINK_MESSAGE);
    $notification = Notification::sent($user, ResetPassword::class)->sole();
    $url = $notification->toMail($user)->actionUrl;
    expect($url)->toStartWith('https://institution.example/reset-password?');
    expect(parse_url($url, PHP_URL_PATH))->toBe('/reset-password');
    expect(parse_url($url, PHP_URL_QUERY))->not->toContain($notification->token);
    expect(parse_url($url, PHP_URL_FRAGMENT))->toBe('token='.$notification->token);
    $token = $notification->token;
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email, 'token' => $token]);
    $this->get(route('password.reset', ['email' => $user->email]))->assertOk()->assertDontSee($token);
    $this->post(route('password.update'), recoveryTestPayload($user, $token))->assertRedirect(route('login'));
    expect(Hash::check('Recovered-private-password-42', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->google2fa_enabled)->toBeTrue();
    expect($user->fresh()->google2fa_secret)->toBe($secret);
    expect($user->fresh()->auth_version)->toBe(1);
    $this->assertGuest();
    $this->postJson(route('password.update'), recoveryTestPayload($user, $token))->assertUnprocessable();
});

test('unknown and suspended email reset requests have the same generic response and send nothing', function (string $kind): void {
    $user = securityTestUser(attributes: ['status' => 'suspended']);
    $email = $kind === 'unknown' ? 'missing@example.test' : $user->email;
    $this->from(route('password.request'))->post(route('password.email'), ['email' => $email])
        ->assertRedirect(route('password.request'))->assertSessionHas('status', AccountRecoveryService::LINK_MESSAGE);
    Notification::assertNothingSent();
})->with(['unknown', 'suspended']);

test('reset links expire and cannot be used for a different account', function (): void {
    $user = securityTestUser();
    $other = securityTestUser();
    $token = Password::createToken($user);
    $before = $user->password;
    $this->postJson(route('password.update'), recoveryTestPayload($other, $token))->assertUnprocessable();
    $this->travel(61)->minutes();
    $this->postJson(route('password.update'), recoveryTestPayload($user, $token))->assertUnprocessable();
    expect($user->fresh()->password)->toBe($before);
});

test('recovery refuses log and log-fallback mailers before creating tokens', function (string $name): void {
    config([
        'mail.default' => $name,
        'mail.mailers.unsafe.transport' => 'failover',
        'mail.mailers.unsafe.mailers' => ['smtp', 'log'],
    ]);
    $user = securityTestUser();
    $this->postJson(route('password.email'), ['email' => $user->email])->assertStatus(503);
    Notification::assertNothingSent();
    $this->assertDatabaseCount('password_reset_tokens', 0);
})->with(['log', 'unsafe']);

test('admin-assisted recovery follows all four role target boundaries', function (string $actorRole, string $targetRole): void {
    $actor = securityTestUser($actorRole);
    $target = securityTestUser($targetRole);
    $allowed = in_array($targetRole, Role::manageableNames($actor), true);
    $secret = $target->google2fa_secret;
    $response = $this->actingAs($actor)->from(route('users.edit', $target))->post(route('users.recovery', $target), [
        'current_password' => 'password', 'code' => securityTestOtp($actor), 'identity_confirmed' => 1,
    ]);
    if ($allowed) {
        $response->assertRedirect(route('users.edit', $target))->assertSessionHasNoErrors();
        Notification::assertSentTo($target, ResetPassword::class);
        expect($target->fresh()->recovery_requested_by)->toBe($actor->id);
    } else {
        $response->assertForbidden();
        Notification::assertNothingSent();
    }
    expect($target->fresh()->google2fa_secret)->toBe($secret);
    expect($target->fresh()->google2fa_enabled)->toBeTrue();
})->with(Role::NAMES)->with(Role::NAMES);

test('verified assisted recovery resets the authenticator only after email redemption', function (): void {
    $admin = securityTestUser('admin');
    $target = securityTestUser();
    $this->actingAs($admin)->from(route('users.edit', $target))->post(route('users.recovery', $target), [
        'current_password' => 'password', 'code' => securityTestOtp($admin), 'identity_confirmed' => 1,
    ])->assertRedirect(route('users.edit', $target));
    $token = Notification::sent($target, ResetPassword::class)->sole()->token;
    expect($target->fresh()->google2fa_enabled)->toBeTrue();
    $this->post(route('logout'));
    $this->post(route('password.update'), recoveryTestPayload($target, $token))->assertRedirect(route('login'));
    expect($target->fresh()->google2fa_enabled)->toBeFalse();
    expect($target->fresh()->google2fa_secret)->toBeNull();
    expect($target->fresh()->two_factor_recovery_token_hash)->toBeNull();
    $this->post(route('login.submit'), ['email' => $target->email, 'password' => 'Recovered-private-password-42'])->assertRedirect(route('2fa.setup'));
    $this->get(route('classes.index'))->assertRedirect(route('2fa.setup'));
});

test('assisted recovery requires actor password OTP identity confirmation and the registered email', function (): void {
    $admin = securityTestUser('admin');
    $target = securityTestUser();
    $base = ['current_password' => 'password', 'code' => securityTestOtp($admin), 'identity_confirmed' => 1];
    $this->actingAs($admin);
    $this->postJson(route('users.recovery', $target), array_replace($base, ['current_password' => 'wrong']))->assertUnprocessable();
    $this->postJson(route('users.recovery', $target), array_replace($base, ['code' => null]))->assertUnprocessable();
    $this->postJson(route('users.recovery', $target), array_replace($base, ['identity_confirmed' => 0]))->assertUnprocessable();
    $this->postJson(route('users.recovery', $target), $base + ['email' => 'attacker@example.test'])->assertUnprocessable();
    Notification::assertNothingSent();
    expect($target->fresh()->two_factor_recovery_token_hash)->toBeNull();
});

test('assisted recovery rechecks administrative authority at redemption', function (): void {
    $admin = securityTestUser('admin');
    $target = securityTestUser();
    $this->actingAs($admin)->post(route('users.recovery', $target), [
        'current_password' => 'password', 'code' => securityTestOtp($admin), 'identity_confirmed' => 1,
    ]);
    $token = Notification::sent($target, ResetPassword::class)->sole()->token;
    $this->post(route('logout'));
    $admin->update(['role_id' => $target->role_id]);
    $this->postJson(route('password.update'), recoveryTestPayload($target, $token))->assertForbidden();
    expect($target->fresh()->google2fa_enabled)->toBeTrue();
    expect(Hash::check('password', $target->fresh()->password))->toBeTrue();
});

test('recovery requests are throttled even for unknown accounts', function (): void {
    for ($i = 0; $i < 3; $i++) {
        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'missing@example.test'])->assertRedirect(route('password.request'));
    }
    $this->postJson(route('password.email'), ['email' => 'missing@example.test'])->assertStatus(429);
    Notification::assertNothingSent();
});

test('email changes revoke reset tokens rather than transferring them to a reused email', function (): void {
    $admin = securityTestUser('admin');
    $target = securityTestUser();
    $token = Password::createToken($target);
    $oldEmail = $target->email;
    $this->actingAs($admin)->putJson(route('users.update', $target), [
        'name' => $target->name, 'email' => 'changed@example.test', 'role_id' => $target->role_id, 'status' => 'active',
    ])->assertRedirect(route('users.index'));
    $replacement = securityTestUser(attributes: ['email' => $oldEmail]);
    $this->post(route('logout'));
    $this->postJson(route('password.update'), recoveryTestPayload($replacement, $token))->assertUnprocessable();
    expect(Hash::check('password', $replacement->fresh()->password))->toBeTrue();
});

test('mail transport failures are sanitized and roll back recovery tokens', function (): void {
    $user = securityTestUser();
    \Illuminate\Support\Facades\Log::spy();
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('PRIVATE_RECOVERY_MESSAGE_CONTENT'));
    $this->postJson(route('password.email'), ['email' => $user->email])->assertStatus(503)
        ->assertDontSee('PRIVATE_RECOVERY_MESSAGE_CONTENT');
    $this->assertDatabaseCount('password_reset_tokens', 0);
    \Illuminate\Support\Facades\Log::shouldNotHaveReceived('error');
});

test('invalid reset tokens and new passwords are never flashed into session input', function (): void {
    $user = securityTestUser();
    $this->from(route('password.reset'))->post(route('password.update'), recoveryTestPayload($user, 'PRIVATE_INVALID_TOKEN'))
        ->assertRedirect(route('password.reset'))->assertSessionHasErrors('email');
    expect(session('_old_input.token'))->toBeNull();
    expect(session('_old_input.password'))->toBeNull();
    expect(session('_old_input.password_confirmation'))->toBeNull();
});

test('the reset page transfers the fragment token to the form and removes it from browser history', function (): void {
    $script = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const html = fs.readFileSync(process.argv[1], 'utf8');
const script = html.match(/<script>([\s\S]*?)<\/script>/)[1];
const input = { value: '' };
let replaced;
vm.runInNewContext(script, {
    URLSearchParams,
    window: { location: { hash: '#token=opaque-test-token', pathname: '/reset-password', search: '?email=student%40example.test' } },
    document: { getElementById: (id) => { assert.equal(id, 'reset-token'); return input; } },
    history: { replaceState: (_state, _title, url) => { replaced = url; } },
});
assert.equal(input.value, 'opaque-test-token');
assert.equal(replaced, '/reset-password?email=student%40example.test');
JS;
    $process = new \Symfony\Component\Process\Process(['node', '-e', $script, resource_path('views/auth/reset-password.blade.php')]);
    $process->mustRun();
    expect($process->isSuccessful())->toBeTrue();
});
