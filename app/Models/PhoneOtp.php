<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneOtp extends Model
{
    protected $fillable = ['phone','otp','attempts','expires_at'];
    protected $casts    = ['expires_at' => 'datetime'];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(string $otp): bool
    {
        return !$this->isExpired() && $this->otp === $otp && $this->attempts < 5;
    }
}
