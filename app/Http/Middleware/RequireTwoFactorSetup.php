<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorSetup
{
    //If the user is logged in but 2FA is not enabled yet, Laravel sends them to setup first.
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        if ($request->is('setup-2fa') || $request->is('verify-2fa') || $request->is('logout')) {
            return $next($request);
        }

        if (! $request->user()->google2fa_enabled) {
            return redirect()->route('2fa.setup');
        }

        return $next($request);
    }
}