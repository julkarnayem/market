<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetEdit extends Model
{
    protected $fillable = [
        'asset_id','requested_by','old_values','new_values',
        'status','reviewed_by','reviewed_at','review_note',
    ];
    protected $casts = ['old_values'=>'array','new_values'=>'array','reviewed_at'=>'datetime'];

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class,'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class,'reviewed_by'); }
}
