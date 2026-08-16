<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryAttribute extends Model
{
    protected $fillable = [
        'category_id','key','label','type','options',
        'is_required','is_filterable','is_active',
        'position','validation_rules','placeholder','unit',
    ];
    protected $casts = [
        'options'=>'array','is_required'=>'boolean',
        'is_filterable'=>'boolean','is_active'=>'boolean','position'=>'integer',
    ];

    public const TYPES = ['text','number','decimal','boolean','select','multiselect','date','url'];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function values(): HasMany { return $this->hasMany(AssetAttributeValue::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }

    /**
     * Always return options as array, regardless of stored format.
     * Handles: null → [], '' → [], JSON string → array, already array → array
     */
    public function safeOptions(): array
    {
        $opts = $this->options;
        if (empty($opts)) return [];
        if (is_array($opts)) return $opts;
        if (is_string($opts)) {
            $decoded = json_decode($opts, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
