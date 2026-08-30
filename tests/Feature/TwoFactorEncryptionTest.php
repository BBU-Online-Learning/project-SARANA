<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

test('new two factor secrets are encrypted at rest and hidden from serialization', function (): void {
    $user = securityTestUser();
    $raw = $user->getRawOriginal('two_factor_secret_encrypted');
    expect($user->getRawOriginal('google2fa_secret'))->toBeNull();
    expect($raw)->not->toBe($user->google2fa_secret);
    expect(Crypt::decryptString($raw))->toBe($user->google2fa_secret);
    expect($user->toArray())->not->toHaveKeys(['google2fa_secret', 'two_factor_secret_encrypted', 'two_factor_recovery_token_hash']);
});

test('legacy plaintext secrets remain readable and migrate idempotently without changing the OTP', function (): void {
    $user = securityTestUser();
    $secret = $user->google2fa_secret;
    User::whereKey($user->id)->update(['google2fa_secret' => $secret, 'two_factor_secret_encrypted' => null]);
    expect($user->fresh()->google2fa_secret)->toBe($secret);
    $this->artisan('auth:encrypt-two-factor-secrets')->assertSuccessful();
    expect($user->fresh()->getRawOriginal('google2fa_secret'))->toBe($secret);
    $this->artisan('auth:encrypt-two-factor-secrets', ['--commit' => true])->assertSuccessful();
    $encrypted = $user->fresh()->getRawOriginal('two_factor_secret_encrypted');
    expect($user->fresh()->getRawOriginal('google2fa_secret'))->toBeNull();
    expect($user->fresh()->google2fa_secret)->toBe($secret);
    $this->artisan('auth:encrypt-two-factor-secrets', ['--commit' => true])->assertSuccessful();
    expect($user->fresh()->getRawOriginal('two_factor_secret_encrypted'))->toBe($encrypted);
});

test('conflicting corrupt and unrecognized records are refused without disclosing or overwriting them', function (string $kind): void {
    $user = securityTestUser();
    $secret = $user->google2fa_secret;
    $encrypted = match ($kind) {
        'conflicting' => Crypt::encryptString((new \PragmaRX\Google2FA\Google2FA)->generateSecretKey()),
        'corrupt' => 'NOT_VALID_CIPHERTEXT',
        default => null,
    };
    User::whereKey($user->id)->update([
        'google2fa_secret' => $kind === 'unrecognized' ? 'do-not-overwrite-this-record' : $secret,
        'two_factor_secret_encrypted' => $encrypted,
    ]);
    $before = $user->fresh()->getAttributes();
    expect(Artisan::call('auth:encrypt-two-factor-secrets', ['--commit' => true]))->toBe(1);
    expect(Artisan::output())->not->toContain($secret)->not->toContain('NOT_VALID_CIPHERTEXT')->not->toContain('do-not-overwrite-this-record');
    expect($user->fresh()->getAttributes())->toBe($before);
})->with(['conflicting', 'corrupt', 'unrecognized']);
