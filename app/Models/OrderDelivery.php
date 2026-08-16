<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OrderDelivery extends Model
{
    protected $fillable = [
        'order_id','delivered_by','delivery_note','delivery_data','attachment_path','attachment_disk',
    ];
    protected $hidden = ['delivery_data']; // Never accidentally serialise credentials

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function deliveredByUser(): BelongsTo { return $this->belongsTo(User::class, 'delivered_by'); }

    public function attachmentUrl(): ?string
    {
        if (!$this->attachment_path) return null;
        return route('orders.delivery.attachment', $this->order_id);
    }
}
