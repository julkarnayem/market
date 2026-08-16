<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id', 'user_id', 'type', 'amount',
        'available_after', 'pending_after',
        'reference_type', 'reference_id', 'available_at', 'meta', 'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',           // signed poisha
            'available_after' => 'integer',  // poisha
            'pending_after' => 'integer',    // poisha
            'available_at' => 'datetime',
            'meta' => 'array',
            'type' => TransactionType::class,
        ];
    }

    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reference(): MorphTo { return $this->morphTo(); }
}
