<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AccountRecoveryService
{
    public const LINK_MESSAGE = 'If this active account is eligible, a reset link will be sent to its registered email.';

    public function __construct(private AccountManagementService $accounts, private AuthSecurityService $security) {}

    public function sendLink(string $email): void
    {
        $this->assertMailer();
        $this->accounts->synchronized(function () use ($email): void {
            $this->send(['email' => $email, 'status' => 'active']);
        });
    }

    public function requestAssistedRecovery(User $actor, User $target, #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $code): void
    {
        $this->assertMailer();
        $this->accounts->synchronized(function () use ($actor, $target, $password, $code): void {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            Gate::forUser($actor)->authorize('update', $target);
            abort_unless($actor->google2fa_enabled && ! $actor->must_change_password && $target->status === 'active', 403);
            $this->security->verifyCredentials($actor, $password, $code);
            $this->send(['email' => $target->email, 'status' => 'active'], $actor->id);
        });
    }

    private function send(array $credentials, ?int $actorId = null): void
    {
        Password::broker()->sendResetLink($credentials, function (User $user, #[\SensitiveParameter] string $token) use ($actorId): void {
            $user->two_factor_recovery_token_hash = $actorId ? hash('sha256', $token) : null;
            $user->recovery_requested_by = $actorId;
            $user->save();
            try {
                $user->sendPasswordResetNotification($token);
            } catch (\Throwable) {
                // Transport exceptions can contain the entire email, including its bearer token.
                throw new HttpException(503, 'Recovery delivery is unavailable. Contact the institution.');
            }
        });
    }

    public function reset(#[\SensitiveParameter] array $credentials): string
    {
        return $this->accounts->synchronized(function () use ($credentials): string {
            return Password::broker()->reset($credentials + ['status' => 'active'], function (User $user, #[\SensitiveParameter] string $password) use ($credentials): void {
                if ($user->two_factor_recovery_token_hash
                    && hash_equals($user->two_factor_recovery_token_hash, hash('sha256', $credentials['token']))) {
                    $actor = User::find($user->recovery_requested_by);
                    abort_unless($actor && Gate::forUser($actor)->allows('update', $user), 403);
                    $user->google2fa_secret = null;
                    $user->google2fa_enabled = false;
                    $user->two_factor_last_used_step = null;
                }

                $user->password = $password;
                $user->must_change_password = false;
                $this->security->invalidateOtherSessions($user);
                $user->save();
                event(new PasswordReset($user));
            });
        });
    }

    private function assertMailer(): void
    {
        $name = config('mail.default');
        abort_unless($this->safeMailer($name), 503, 'Recovery email delivery is not configured. Contact the institution.');
    }

    private function safeMailer(?string $name, array $seen = []): bool
    {
        if (! $name || in_array($name, $seen, true)) {
            return false;
        }
        $mailer = config('mail.mailers.'.$name, []);
        $transport = $mailer['transport'] ?? null;
        if ($transport === 'array') {
            return app()->environment('testing');
        }
        if (in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend'], true)) {
            return true;
        }
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $mailer['mailers'] ?? [];

            return $children !== [] && collect($children)->every(fn ($child): bool => $this->safeMailer($child, [...$seen, $name]));
        }

        return false;
    }
}
