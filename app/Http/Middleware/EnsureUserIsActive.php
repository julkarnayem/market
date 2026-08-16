<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && ! $user->canTransact()) {
            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'Your account has been suspended. Contact support for assistance.');
        }

        return $next($request);
    }
}
