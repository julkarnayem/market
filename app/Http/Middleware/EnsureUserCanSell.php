<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserCanSell
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::user()?->canSell()) {
            return redirect()->route('dashboard.verification')
                ->with('error', 'You must complete seller verification before performing this action.');
        }

        return $next($request);
    }
}
