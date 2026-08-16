<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Support\Traits\HasRolesAndPermissions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes, HasRolesAndPermissions;

    protected $fillable = [
        'bio',
        'name', 'username', 'email', 'phone', 'password',
        'profile_photo_path', 'status', 'verification_status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'verification_status' => VerificationStatus::class,
        ];
    }

    // ---- State helpers (business rules enforced server-side) ----
    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    /** Gate on ALL major marketplace activity. */
    public function canTransact(): bool
    {
        return $this->status?->canTransact() ?? false;
    }

    /** Selling is verification-gated; buying is not. */
    public function canSell(): bool
    {
        return $this->canTransact()
            && $this->verification_status === VerificationStatus::Approved;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function isVerifiedSeller(): bool
    {
        return $this->verification_status === VerificationStatus::Approved;
    }

    // ---- Relationships ----
    public function wallet(): HasOne { return $this->hasOne(Wallet::class); }
    public function sellerVerification(): HasOne { return $this->hasOne(SellerVerification::class)->latestOfMany(); }
    public function listings(): HasMany { return $this->hasMany(Asset::class); }
    public function purchases(): HasMany { return $this->hasMany(Order::class, 'buyer_user_id'); }
    public function sales(): HasMany { return $this->hasMany(Order::class, 'seller_user_id'); }
    public function withdrawals(): HasMany { return $this->hasMany(Withdrawal::class); }
    public function favorites(): HasMany { return $this->hasMany(Favorite::class); }
    public function supportTickets(): HasMany { return $this->hasMany(SupportTicket::class); }
    public function walletTransactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
    public function sentOffers(): HasMany { return $this->hasMany(Offer::class, 'buyer_user_id'); }
    public function receivedOffers(): HasMany { return $this->hasMany(Offer::class, 'seller_user_id'); }
    public function conversations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany {
        return $this->belongsToMany(Conversation::class, 'conversation_participants');
    }
    public function auditLogs(): HasMany     { return $this->hasMany(AuditLog::class, 'user_id'); }
    public function reviewsReceived(): HasMany { return $this->hasMany(Review::class, 'seller_id'); }
    public function reviewsGiven(): HasMany    { return $this->hasMany(Review::class, 'reviewer_id'); }
    public function verifications(): HasMany { return $this->hasMany(SellerVerification::class)->orderBy('attempt_number'); }
}
