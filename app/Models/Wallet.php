<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = ['user_id','available_balance','pending_balance','currency'];
    protected $casts    = ['available_balance'=>'integer','pending_balance'=>'integer'];

    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function transactions(): HasMany   { return $this->hasMany(WalletTransaction::class); }

    public function totalBalance(): int { return $this->available_balance + $this->pending_balance; }
    public function canWithdraw(int $amount): bool { return $this->available_balance >= $amount; }
}
