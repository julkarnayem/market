<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NoIndex
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (method_exists($response, 'header')) {
            $response->header('X-Robots-Tag', 'noindex, nofollow');
        }
        return $response;
    }
}
