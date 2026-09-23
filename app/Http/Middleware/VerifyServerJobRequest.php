<?php

namespace App\Http\Middleware;

use App\Support\ServerJobSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyServerJobRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('server_jobs.secret');
        $allowed = config('server_jobs.allowed_ips');
        $remote = (string) $request->server->get('REMOTE_ADDR', '');

        if (strlen($secret) < 32) {
            abort(404);
        }

        if (is_array($allowed) && $allowed !== [] && ! in_array($remote, $allowed, true)) {
            abort(404);
        }

        $timestamp = trim((string) $request->header(ServerJobSignature::TIME_HEADER));
        $mac = strtolower(trim((string) $request->header(ServerJobSignature::MAC_HEADER)));
        $skew = (int) config('server_jobs.skew_seconds', 60);

        if (! ctype_digit($timestamp) || strlen($timestamp) < 10 || strlen($timestamp) > 12) {
            abort(404);
        }

        if (abs(time() - (int) $timestamp) > $skew) {
            abort(404);
        }

        $expected = ServerJobSignature::sign($secret, $timestamp, $request->path(), $request->getContent());
        if (strlen($mac) !== 64 || ! hash_equals($expected, $mac)) {
            abort(404);
        }

        return $next($request);
    }
}
