<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Sets production security headers on every response.
 * NOTE: CSP is kept permissive to avoid breaking existing frontend (CDN fonts, Vite).
 * Tighten in Part 10 after frontend audit.
 */
class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only add to HTML responses to avoid breaking API/file downloads
        if (!method_exists($response, 'header')) return $response;

        $response
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS only when running HTTPS (set by proxy header or env)
        if ($request->isSecure() || config('app.env') === 'production') {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content-Security-Policy — permissive to maintain functionality.
        // Tighten after testing in Part 10.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",  // unsafe-eval for Alpine.js
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
        ]);
        $response->header('Content-Security-Policy', $csp);

        return $response;
    }
}
