<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetView extends Model
{
    protected $fillable = ['asset_id','user_id','viewer_hash','viewed_date'];
    protected $casts    = ['viewed_date'=>'date'];
    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
}
