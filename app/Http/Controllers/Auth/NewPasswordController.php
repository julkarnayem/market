<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class NewPasswordController extends Controller
{
    use OtpHelper;

    public function create()
    {
        if (!session('reset_phone')) return redirect()->route('password.request');
        return view('auth.reset-verify');
    }

    public function verifyOtp(Request $request)
    {
        $phone = session('reset_phone');
        if (!$phone) return redirect()->route('password.request');

        $data = $request->validate([
            'otp' => ['required','string','size:6','regex:/^\d{6}$/'],
        ], [
            'otp.size'  => 'OTP must be exactly 6 digits.',
            'otp.regex' => 'OTP must be 6 digits only.',
        ]);

        $error = $this->checkOtp($phone, $data['otp']);
        if ($error) {
            return back()->withErrors(['otp' => $error]);
        }

        session(['reset_phone_verified' => true]);
        return redirect()->route('password.reset-form');
    }

    public function showReset()
    {
        if (!session('reset_phone') || !session('reset_phone_verified')) {
            return redirect()->route('password.request');
        }
        return view('auth.reset-password');
    }

    public function store(Request $request)
    {
        $phone = session('reset_phone');
        if (!$phone || !session('reset_phone_verified')) {
            return redirect()->route('password.request');
        }

        $request->validate([
            'password' => ['required','confirmed', Password::min(6)],
        ]);

        $user = User::where('phone', $phone)->firstOrFail();
        $user->update(['password' => $request->password]);

        session()->forget(['reset_phone','reset_phone_verified']);

        Auth::login($user);
        return redirect()->route('home')->with('success', 'Password reset successfully.');
    }
}
