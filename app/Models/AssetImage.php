<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssetImage extends Model
{
    protected $fillable = [
        'asset_id','disk','path','original_name','mime_type','size_bytes','is_cover','sort_order',
    ];
    protected $casts = ['is_cover'=>'boolean','size_bytes'=>'integer','sort_order'=>'integer'];

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function delete(): ?bool
    {
        Storage::disk($this->disk)->delete($this->path);
        return parent::delete();
    }
}
