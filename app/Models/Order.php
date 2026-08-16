<?php
namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'reference','order_number',
        'buyer_user_id','seller_user_id','asset_id','offer_id',
        'quantity','unit_price','subtotal',
        'seller_fee_bp','seller_fee_amount',
        'buyer_fee_enabled','buyer_fee_type','buyer_fee_bp','buyer_fee_amount',
        'platform_commission','buyer_total','seller_earning',
        'currency','status','payment_status','delivery_status','dispute_status',
        'payment_gateway','payment_reference',
        'paid_at','delivered_at','buyer_received_at','completed_at',
        'auto_complete_at','auto_completed_at','earning_release_at','seller_earning_available_at','earning_released',
    ];
    protected function casts(): array {
        return [
            'quantity'=>'integer',
            'unit_price'=>'integer','subtotal'=>'integer',
            'seller_fee_bp'=>'integer','seller_fee_amount'=>'integer',
            'buyer_fee_enabled'=>'boolean','buyer_fee_bp'=>'integer',
            'buyer_fee_amount'=>'integer','platform_commission'=>'integer',
            'buyer_total'=>'integer','seller_earning'=>'integer',
            'paid_at'=>'datetime','delivered_at'=>'datetime',
            'buyer_received_at'=>'datetime','completed_at'=>'datetime',
            'auto_complete_at'=>'datetime','auto_completed_at'=>'datetime',
            'earning_release_at'=>'datetime','seller_earning_available_at'=>'datetime','earning_released'=>'boolean',
            'status'=>OrderStatus::class,
        ];
    }

    public function buyer(): BelongsTo        { return $this->belongsTo(User::class, 'buyer_user_id'); }
    public function seller(): BelongsTo       { return $this->belongsTo(User::class, 'seller_user_id'); }
    public function asset(): BelongsTo        { return $this->belongsTo(Asset::class); }
    public function offer(): BelongsTo        { return $this->belongsTo(Offer::class); }
    public function statusHistory(): HasMany  { return $this->hasMany(OrderStatusHistory::class); }
    public function conversation(): HasOne    { return $this->hasOne(Conversation::class); }
    public function dispute(): HasOne         { return $this->hasOne(Dispute::class); }
    public function payments(): HasMany       { return $this->hasMany(Payment::class); }
    public function latestPayment(): HasOne   { return $this->hasOne(Payment::class)->latest(); }
    public function delivery(): HasOne        { return $this->hasOne(OrderDelivery::class); }

    public function isOwnedByBuyer(int $userId): bool  { return $this->buyer_user_id === $userId; }
    public function isOwnedBySeller(int $userId): bool { return $this->seller_user_id === $userId; }
    public function earningIsLocked(): bool {
        return $this->seller_earning_available_at !== null && $this->seller_earning_available_at->isFuture();
    }
}
