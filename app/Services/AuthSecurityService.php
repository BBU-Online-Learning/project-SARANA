<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthSecurityService
{
    public const CHALLENGE_SECONDS = 300;

    public const SETUP_SECONDS = 600;

    public function __construct(private AccountManagementService $accounts) {}

    public function withUser(int $id, Closure $callback): mixed
    {
        return $this->accounts->synchronized(function () use ($id, $callback): mixed {
            $user = User::query()->lockForUpdate()->find($id);
            abort_unless($user && $user->status === 'active', 403);

            return $callback($user);
        });
    }

    public function consumeOtp(User $user, #[\SensitiveParameter] string $code, ?string $setupSecret = null): void
    {
        try {
            $secret = $setupSecret ?? $user->google2fa_secret;
            $step = $secret ? (new Google2FA)->verifyKeyNewer(
                $secret, $code, $setupSecret ? 0 : ($user->two_factor_last_used_step ?? 0),
                1, intdiv(now()->timestamp, 30)
            ) : false;
        } catch (\Throwable) {
            $step = false;
        }

        if ($step === false) {
            throw ValidationException::withMessages(['code' => 'Invalid or already used code. Wait for a fresh authenticator code.']);
        }

        $user->two_factor_last_used_step = $step;
        $user->save();
    }

    public function verifyCredentials(User $user, #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $code): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Verification failed.']);
        }
        if ($user->google2fa_enabled) {
            $this->consumeOtp($user, $code ?? '');
        }
    }

    public function invalidateOtherSessions(User $user): void
    {
        $user->auth_version++;
        $user->setRememberToken(Str::random(60));
        $user->two_factor_recovery_token_hash = null;
        $user->recovery_requested_by = null;
    }

    public function authenticate(Request $request, User $user, bool $remember = false): void
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($user, $remember);
        $request->session()->put('auth_version', $user->auth_version);
    }

    public function challengeUser(Request $request): ?User
    {
        if ((int) $request->session()->get('pending_2fa_expires_at', 0) <= now()->timestamp) {
            $this->clearChallenge($request);

            return null;
        }
        $user = User::find($request->session()->get('pending_2fa_user_id'));
        if (! $user || $user->status !== 'active' || ! $user->google2fa_enabled
            || $user->auth_version !== $request->session()->get('pending_2fa_version')) {
            $this->clearChallenge($request);

            return null;
        }

        return $user;
    }

    public function clearChallenge(Request $request): void
    {
        $request->session()->forget(['pending_2fa_user_id', 'pending_2fa_expires_at', 'pending_2fa_version', 'pending_2fa_remember']);
    }

    public function setupSecret(Request $request): ?string
    {
        if ($request->session()->get('pending_2fa_setup_user_id') !== $request->user()->id
            || (int) $request->session()->get('pending_2fa_setup_expires_at', 0) <= now()->timestamp) {
            return null;
        }

        try {
            return Crypt::decryptString($request->session()->get('pending_2fa_secret', ''));
        } catch (\Throwable) {
            return null;
        }
    }
}
