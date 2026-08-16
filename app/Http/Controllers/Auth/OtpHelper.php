<?php
namespace App\Http\Controllers\Auth;

use App\Models\PhoneBlock;
use App\Models\PhoneOtp;
use App\Contracts\SmsServiceInterface;
use Illuminate\Http\RedirectResponse;

trait OtpHelper
{
    /**
     * Check if phone is blocked. Returns redirect with error or null if OK.
     */
    protected function checkBlock(string $phone, string $backRoute): ?RedirectResponse
    {
        $block = PhoneBlock::getBlock($phone);
        if ($block) {
            $hoursLeft = ceil(now()->diffInMinutes($block->blocked_until) / 60);
            return back()->withErrors([
                'otp' => "This number is blocked for 24 hours due to too many failed attempts. Try again in {$hoursLeft} hour(s).",
            ]);
        }
        return null;
    }

    /**
     * Verify OTP. Returns error redirect or null if valid.
     * Blocks phone after 2 failed attempts.
     */
    protected function checkOtp(string $phone, string $inputOtp): ?string
    {
        // Already blocked?
        if (PhoneBlock::isBlocked($phone)) {
            $block = PhoneBlock::getBlock($phone);
            $hours = ceil(now()->diffInMinutes($block->blocked_until) / 60);
            return "This number is blocked for 24 hours due to too many failed attempts. Try again in {$hours} hour(s).";
        }

        $record = PhoneOtp::where('phone', $phone)
            ->where('expires_at', '>', now())
            ->latest()->first();

        if (!$record) {
            return 'OTP expired. Please go back and request a new one.';
        }

        // Increment attempts BEFORE checking
        $record->increment('attempts');

        if ($record->otp !== $inputOtp) {
            $remaining = 2 - $record->attempts;

            if ($record->attempts >= 2) {
                // Block the phone for 24 hours
                PhoneBlock::block($phone);
                // Delete OTP record
                $record->delete();
                return 'Too many incorrect attempts. This number has been blocked for 24 hours.';
            }

            return "Incorrect OTP. {$remaining} attempt(s) remaining.";
        }

        // Correct OTP — clean up
        $record->delete();
        return null; // success
    }

    /**
     * Send OTP SMS.
     */
    protected function sendOtpSms(string $phone, string $otp, string $context = 'OTP'): void
    {
        try {
            app(SmsServiceInterface::class)->send(
                $phone,
                config('app.name')." {$context}: {$otp}. Valid for 10 minutes. Do not share this code."
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("OTP SMS failed [{$phone}]: ".$e->getMessage());
        }
    }
}
