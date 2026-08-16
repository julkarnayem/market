<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAttributeValue extends Model
{
    protected $fillable = ['asset_id', 'category_attribute_id', 'value'];

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function attribute(): BelongsTo { return $this->belongsTo(CategoryAttribute::class, 'category_attribute_id'); }
}
