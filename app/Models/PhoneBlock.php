<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneBlock extends Model
{
    protected $fillable = ['phone','reason','blocked_until'];
    protected $casts    = ['blocked_until' => 'datetime'];

    public static function isBlocked(string $phone): bool
    {
        return static::where('phone', $phone)
            ->where('blocked_until', '>', now())
            ->exists();
    }

    public static function getBlock(string $phone): ?self
    {
        return static::where('phone', $phone)
            ->where('blocked_until', '>', now())
            ->first();
    }

    public static function block(string $phone, string $reason = 'too_many_otp_failures'): void
    {
        static::updateOrCreate(
            ['phone' => $phone],
            ['reason' => $reason, 'blocked_until' => now()->addHours(24)]
        );
    }
}
