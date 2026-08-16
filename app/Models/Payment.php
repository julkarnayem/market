<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id','gateway','gateway_payment_id','gateway_transaction_id',
        'amount','currency','status','gateway_response','paid_at',
    ];
    protected $casts = ['amount'=>'integer','paid_at'=>'datetime'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    public function isPaid(): bool  { return $this->status === 'paid'; }
    public function isPending(): bool { return $this->status === 'pending'; }
}
