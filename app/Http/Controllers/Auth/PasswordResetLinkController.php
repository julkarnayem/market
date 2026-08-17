<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PhoneBlock;
use App\Models\PhoneOtp;
use App\Models\User;
use Inertia\Inertia;

class PasswordResetLinkController extends Controller
{
    use OtpHelper;

    public function create()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'phone' => ['required','string','regex:/^01[3-9]\d{8}$/','max:20'],
        ], [
            'phone.regex' => 'Enter a valid Bangladeshi mobile number.',
        ]);

        $phone = $data['phone'];

        if (!User::where('phone', $phone)->exists()) {
            return back()->withErrors(['phone' => 'No account found with this phone number.'])->withInput();
        }

        // 24h block check
        if (PhoneBlock::isBlocked($phone)) {
            $block = PhoneBlock::getBlock($phone);
            $hours = ceil(now()->diffInMinutes($block->blocked_until) / 60);
            return back()->withErrors(['phone' => "This number is blocked for 24 hours. Try again in {$hours} hour(s)."])->withInput();
        }

        // No resend before expiry
        $existing = PhoneOtp::where('phone', $phone)->where('expires_at', '>', now())->latest()->first();
        if ($existing) {
            $wait = ceil(now()->diffInSeconds($existing->expires_at, false) / 60);
            return back()->withErrors(['phone' => "An OTP was already sent. Please wait {$wait} minute(s) before requesting again."])->withInput();
        }

        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        PhoneOtp::create(['phone' => $phone, 'otp' => $otp, 'expires_at' => now()->addMinutes(10)]);
        $this->sendOtpSms($phone, $otp, 'Password Reset OTP');

        session(['reset_phone' => $phone]);
        return redirect()->route('password.verify');
    }
}
