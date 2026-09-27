<?php

namespace App\Services;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class TwoFactorTrustedDeviceService
{
    public const COOKIE_NAME = 'bbu_two_factor_trusted_device';

    public const LIFETIME_DAYS = 30;

    public function isTrusted(Request $request, User $user): bool
    {
        $value = $request->cookie(self::COOKIE_NAME);
        if (! is_string($value) || ! str_contains($value, '|')) {
            return false;
        }

        [$deviceId, $token] = explode('|', $value, 2);
        if (! ctype_digit($deviceId) || strlen($token) < 40) {
            return false;
        }

        $device = TwoFactorTrustedDevice::query()
            ->whereKey((int) $deviceId)
            ->where('user_id', $user->id)
            ->where('auth_version', $user->auth_version)
            ->where('expires_at', '>', now())
            ->first();

        $userAgentHash = $this->userAgentHash($request);
        if (! $device || ! hash_equals($device->token_hash, hash('sha256', $token))
            || ($device->user_agent_hash && ! hash_equals($device->user_agent_hash, $userAgentHash))) {
            return false;
        }

        $device->forceFill(['last_used_at' => now()])->save();

        return true;
    }

    public function createCookie(Request $request, User $user): Cookie
    {
        TwoFactorTrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '<=', now())
            ->delete();

        $token = Str::random(64);
        $device = TwoFactorTrustedDevice::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'auth_version' => $user->auth_version,
            'user_agent_hash' => $this->userAgentHash($request),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::LIFETIME_DAYS),
        ]);

        return cookie(
            self::COOKIE_NAME,
            $device->id.'|'.$token,
            self::LIFETIME_DAYS * 24 * 60,
            config('session.path', '/'),
            config('session.domain'),
            (bool) config('session.secure', false),
            true,
            false,
            config('session.same_site', 'lax'),
        );
    }

    private function userAgentHash(Request $request): string
    {
        return hash('sha256', (string) $request->userAgent());
    }
}
