<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Users\RecoverUserRequest;
use App\Models\User;
use App\Services\AccountRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordRecoveryController extends Controller
{
    public function __construct(private AccountRecoveryService $recovery) {}

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(PasswordResetLinkRequest $request): RedirectResponse
    {
        $this->recovery->sendLink($request->validated('email'));

        return back()->with('status', AccountRecoveryService::LINK_MESSAGE);
    }

    public function show(Request $request): View
    {
        return view('auth.reset-password', ['email' => is_string($request->query('email')) ? $request->query('email') : '']);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $result = $this->recovery->reset($request->validated());
        if ($result !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This reset link is invalid or expired. Request a new one.']);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Password reset. Sign in and complete any required authenticator setup.');
    }

    public function assist(RecoverUserRequest $request, User $user): RedirectResponse
    {
        $this->recovery->requestAssistedRecovery($request->user(), $user, $request->validated('current_password'), $request->validated('code'));

        return back()->with('success', AccountRecoveryService::LINK_MESSAGE);
    }
}
