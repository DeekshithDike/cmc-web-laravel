<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            // form-action also applies to the Location redirect after POST.
            // Login stays on-site; register redirects to NOWPayments checkout.
            $formAction = "'self' https://nowpayments.io https://*.nowpayments.io";
            if (app()->environment('local')) {
                $port = $request->getPort();
                $formAction .= " http://127.0.0.1:{$port} http://localhost:{$port}";
            }

            $policy = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action {$formAction}; object-src 'none'";
            if ($request->secure()) {
                $policy .= '; upgrade-insecure-requests';
            }

            $response->headers->set('Content-Security-Policy', $policy);
        }

        return $response;
    }
}
