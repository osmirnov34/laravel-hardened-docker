<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * OWASP ASVS 5.0.0 §7.3.2 (Absolute Maximum Session Lifetime): caps a session's
 * total lifetime regardless of activity, closing the gap that SESSION_LIFETIME
 * (§7.3.1, inactivity timeout) leaves open on its own.
 */
class EnforceSessionAbsoluteTimeout
{
    /**
     * @param Closure(Request): Response $next
     *
     * @throws RuntimeException If the session cannot be accessed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $maxSeconds = \config('session.absolute_lifetime', 720) * 60;

        if ($session->has('login_at')) {
            $loginAt = Carbon::createFromTimestamp((int) $session->get('login_at'));
            $expired = \now()->diffInSeconds($loginAt, absolute: true) >= $maxSeconds;

            if ($expired) {
                Auth::guard()->logout();
                $session->invalidate();
                $session->regenerateToken();

                return $request->expectsJson()
                    ? \response()->json(['message' => 'Session expired.'], Response::HTTP_UNAUTHORIZED)
                    : \redirect()->route('login');
            }
        } elseif (Auth::check()) {
            $session->put('login_at', \now()->timestamp);
        }

        return $next($request);
    }
}
