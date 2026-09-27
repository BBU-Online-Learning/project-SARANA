<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ValidateFlexibleSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->hasValidSignature()
            || $request->hasValidRelativeSignature()
            || $this->hasValidConfiguredHostSignature($request),
            403,
        );

        return $next($request);
    }

    private function hasValidConfiguredHostSignature(Request $request): bool
    {
        $configuredUrl = rtrim((string) config('app.url'), '/');
        if (! str_starts_with($configuredUrl, 'http://') && ! str_starts_with($configuredUrl, 'https://')) {
            return false;
        }

        $configuredRequest = Request::create(
            $configuredUrl.'/'.$request->path().($request->getQueryString() ? '?'.$request->getQueryString() : ''),
            $request->method(),
        );

        return URL::hasValidSignature($configuredRequest);
    }
}
