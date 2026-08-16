<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DenyFraming
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (method_exists($response, 'header')) {
            $response->header('X-Frame-Options', 'DENY');
            $response->header('Content-Security-Policy', "frame-ancestors 'none'");
        }
        return $response;
    }
}
