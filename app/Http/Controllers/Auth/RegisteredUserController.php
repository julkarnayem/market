<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PhoneBlock;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    use OtpHelper;

    // Step 1: Show phone form
    public function create()
    {
        return view('auth.register');
    }

    // Step 1: Send OTP
    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required','string','regex:/^01[3-9]\d{8}$/','max:20'],
        ], [
            'phone.regex' => 'Enter a valid Bangladeshi mobile number (e.g. 01711234567).',
        ]);

        $phone = $data['phone'];

        // Check if phone already registered
        if (User::where('phone', $phone)->exists()) {
            return back()->withErrors(['phone' => 'This phone number is already registered. Please login.'])->withInput();
        }

        // Check 24h block
        if (PhoneBlock::isBlocked($phone)) {
            $block = PhoneBlock::getBlock($phone);
            $hours = ceil(now()->diffInMinutes($block->blocked_until) / 60);
            return back()->withErrors(['phone' => "This number is blocked for 24 hours. Try again in {$hours} hour(s)."])->withInput();
        }

        // Block resend if valid OTP already exists
        $existing = PhoneOtp::where('phone', $phone)
            ->where('expires_at', '>', now())
            ->latest()->first();
        if ($existing) {
            $wait = ceil(now()->diffInSeconds($existing->expires_at, false) / 60);
            return back()->withErrors(['phone' => "An OTP was already sent. Please wait {$wait} minute(s) before requesting a new one."])->withInput();
        }

        // Generate & save OTP
        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        PhoneOtp::create(['phone' => $phone, 'otp' => $otp, 'expires_at' => now()->addMinutes(10)]);

        $this->sendOtpSms($phone, $otp, 'Signup OTP');

        session(['register_phone' => $phone]);
        return redirect()->route('register.verify');
    }

    // Step 2: Show OTP form
    public function showVerify()
    {
        if (!session('register_phone')) return redirect()->route('register');
        return view('auth.register-verify');
    }

    // Step 2: Verify OTP
    public function verifyOtp(Request $request)
    {
        $phone = session('register_phone');
        if (!$phone) return redirect()->route('register');

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

        session(['register_phone_verified' => true]);
        return redirect()->route('register.details');
    }

    // Step 3: Show details form
    public function showDetails()
    {
        if (!session('register_phone') || !session('register_phone_verified')) {
            return redirect()->route('register');
        }
        return view('auth.register-details');
    }

    // Step 3: Create account
    public function store(Request $request)
    {
        $phone = session('register_phone');
        if (!$phone || !session('register_phone_verified')) {
            return redirect()->route('register')->withErrors(['phone' => 'Please complete phone verification first.']);
        }

        $data = $request->validate([
            'first_name' => ['required','string','max:100'],
            'last_name'  => ['required','string','max:100'],
            'email'      => ['required','email','max:255','unique:users,email'],
            'password'   => ['required','confirmed', Password::min(6)],
        ]);

        $name = trim($data['first_name'].' '.$data['last_name']);

        $user = DB::transaction(function () use ($data, $name, $phone) {
            // Create user first to get ID
            $user = User::create([
                'name'     => $name,
                'username' => 'tmp_'.time(), // temp — updated below
                'email'    => $data['email'],
                'phone'    => $phone,
                'password' => $data['password'],
                'email_verified_at' => null,
            ]);
            // Auto username: firstname.lastname + id (e.g. john.doe042)
            $base     = Str::slug($data['first_name'].'.'.$data['last_name']);
            $username = $base . str_pad($user->id, 3, '0', STR_PAD_LEFT);
            // Ensure unique (very rare collision)
            while (User::where('username', $username)->where('id','!=',$user->id)->exists()) {
                $username = $base . $user->id . rand(1,9);
            }
            $user->update(['username' => $username]);
            Wallet::create(['user_id' => $user->id, 'currency' => 'BDT']);
            return $user;
        });

        session()->forget(['register_phone','register_phone_verified']);
        Auth::login($user);
        return redirect()->route('home');
    }
}
