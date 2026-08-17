<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    public function create() { return Inertia::render('Auth/Login'); }

    public function store(Request $request, AuditLogger $audit)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();

        // Suspended users may log in to view history but are blocked from actions by middleware.
        Auth::user()->update(['last_login_at' => now()]);
        $audit->log('auth.login', Auth::user());

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuditLogger $audit)
    {
        $audit->log('auth.logout', Auth::user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
