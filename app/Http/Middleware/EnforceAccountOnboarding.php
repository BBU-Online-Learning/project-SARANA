<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceAccountOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $request->routeIs('logout', 'login', 'login.submit', 'password.request', 'password.email', 'password.reset', 'password.update')) {
            $response = $next($request);
            if ($request->routeIs('login*', 'password.*', '2fa.*')) {
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('Referrer-Policy', 'no-referrer');
            }

            return $response;
        }
        if (! $user->google2fa_enabled && ! $request->routeIs('2fa.setup', '2fa.setup.submit', 'password.change', 'password.change.submit')) {
            return redirect()->route('2fa.setup');
        }
        if ($user->google2fa_enabled && $user->must_change_password && ! $request->routeIs('password.change', 'password.change.submit')) {
            return redirect()->route('password.change');
        }

        $response = $next($request);
        if ($request->routeIs('password.*', '2fa.*', 'users.edit')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        return $response;
    }
}
