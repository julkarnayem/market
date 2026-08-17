<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailVerificationController extends Controller
{
    public function notice() { return Inertia::render('Auth/VerifyEmail'); }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();
        return redirect(route('dashboard'))->with('success', 'Email verified.');
    }

    public function send(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();

        // A readable sentence rather than the Breeze 'verification-link-sent'
        // sentinel: PublicLayout renders the shared `flash.status` prop verbatim.
        return back()->with('status', 'A new verification link has been sent to your email address.');
    }
}
