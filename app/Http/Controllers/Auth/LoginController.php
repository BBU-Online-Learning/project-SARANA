<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OtpRequest;
use App\Http\Requests\Auth\VerifyAccountRequest;
use App\Models\User;
use App\Services\AuthSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FAQRCode\Google2FA;

class LoginController extends Controller
{
    public function __construct(private AuthSecurityService $security) {}

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $this->security->clearChallenge($request);
        $user = User::where('email', $request->validated('email'))->first();
        if (! $user || $user->status !== 'active' || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($user->google2fa_enabled) {
            $request->session()->put([
                'pending_2fa_user_id' => $user->id,
                'pending_2fa_version' => $user->auth_version,
                'pending_2fa_expires_at' => now()->timestamp + AuthSecurityService::CHALLENGE_SECONDS,
                'pending_2fa_remember' => $request->boolean('remember'),
            ]);

            return redirect()->route('2fa.challenge');
        }

        $this->security->authenticate($request, $user, $request->boolean('remember'));

        return redirect()->route('2fa.setup');
    }

    public function showChangePassword(): View
    {
        return view('auth.change-password');
    }

    public function changePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $this->security->withUser($request->user()->id, function (User $user) use ($request): User {
            $this->security->verifyCredentials($user, $request->validated('current_password'), $request->validated('code'));
            $user->password = $request->validated('password');
            $user->must_change_password = false;
            $this->security->invalidateOtherSessions($user);
            $user->save();

            return $user;
        });
        $request->session()->regenerate();
        $request->session()->put('auth_version', $user->auth_version);

        return $this->redirectAfterLogin($user);
    }

    private function redirectAfterLogin(User $user): RedirectResponse
    {
        if (! $user->google2fa_enabled) {
            return redirect()->route('2fa.setup');
        }
        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->route($user->can('access-admin') ? 'home' : 'chat.index');
    }

    public function showTwoFactorChallenge(Request $request): View|RedirectResponse
    {
        return $this->security->challengeUser($request)
            ? view('auth.verify-2fa') : redirect()->route('login');
    }

    public function verifyTwoFactor(OtpRequest $request): RedirectResponse
    {
        $candidate = $this->security->challengeUser($request);
        if (! $candidate) {
            return redirect()->route('login')->withErrors(['code' => 'Challenge expired. Sign in again.']);
        }

        $user = $this->security->withUser($candidate->id, function (User $user) use ($request): User {
            abort_unless($user->google2fa_enabled && $user->auth_version === $request->session()->get('pending_2fa_version')
                && (int) $request->session()->get('pending_2fa_expires_at', 0) > now()->timestamp, 403);
            $this->security->consumeOtp($user, $request->validated('code'));

            return $user;
        });
        $this->security->authenticate($request, $user, (bool) $request->session()->get('pending_2fa_remember'));

        return $this->redirectAfterLogin($user);
    }

    public function showTwoFactorSetup(Request $request): View|RedirectResponse
    {
        if ($request->user()->google2fa_enabled) {
            return $this->redirectAfterLogin($request->user());
        }
        $secret = $this->security->setupSecret($request);
        if (! $secret) {
            $secret = (new Google2FA)->generateSecretKey();
            $request->session()->put([
                'pending_2fa_secret' => Crypt::encryptString($secret),
                'pending_2fa_setup_user_id' => $request->user()->id,
                'pending_2fa_setup_expires_at' => now()->timestamp + AuthSecurityService::SETUP_SECONDS,
            ]);
        }
        $qrCode = (new Google2FA)->getQRCodeInline(config('app.name'), $request->user()->email, $secret);

        return view('auth.setup-2fa', compact('secret', 'qrCode'));
    }

    public function enableTwoFactor(OtpRequest $request): RedirectResponse
    {
        $secret = $this->security->setupSecret($request);
        if (! $secret) {
            throw ValidationException::withMessages(['code' => 'Setup expired. Open the setup page again.']);
        }
        $user = $this->security->withUser($request->user()->id, function (User $user) use ($request, $secret): User {
            abort_if($user->google2fa_enabled, 403);
            $this->security->consumeOtp($user, $request->validated('code'), $secret);
            $user->google2fa_secret = $secret;
            $user->google2fa_enabled = true;
            $this->security->invalidateOtherSessions($user);
            $user->save();

            return $user;
        });
        $request->session()->forget(['pending_2fa_secret', 'pending_2fa_setup_user_id', 'pending_2fa_setup_expires_at']);
        $request->session()->regenerate();
        $request->session()->put('auth_version', $user->auth_version);

        return redirect()->route($user->must_change_password ? 'password.change' : 'home');
    }

    public function disableTwoFactor(VerifyAccountRequest $request): RedirectResponse
    {
        $user = $this->security->withUser($request->user()->id, function (User $user) use ($request): User {
            abort_unless($user->google2fa_enabled, 403);
            $this->security->verifyCredentials($user, $request->validated('current_password'), $request->validated('code'));
            $user->google2fa_secret = null;
            $user->google2fa_enabled = false;
            $user->two_factor_last_used_step = null;
            $this->security->invalidateOtherSessions($user);
            $user->save();

            return $user;
        });
        $this->security->authenticate($request, $user);

        return redirect()->route('2fa.setup')->with('status', 'Authenticator removed. Set up a new authenticator before continuing.');
    }
}
