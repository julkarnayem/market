<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['order_id','reviewer_id','seller_id','asset_id','rating','comment'];
    protected $casts    = ['rating' => 'integer'];

    public function order(): BelongsTo    { return $this->belongsTo(Order::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class,'reviewer_id'); }
    public function seller(): BelongsTo   { return $this->belongsTo(User::class,'seller_id'); }
    public function asset(): BelongsTo    { return $this->belongsTo(Asset::class); }
}
