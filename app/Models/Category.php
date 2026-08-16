<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'parent_id','name','slug','icon','description',
        'is_active','is_prohibited','is_restricted','position',
    ];
    protected $casts = [
        'is_active'=>'boolean','is_prohibited'=>'boolean','is_restricted'=>'boolean','position'=>'integer',
    ];

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('position'); }
    public function attributes(): HasMany { return $this->hasMany(CategoryAttribute::class)->orderBy('position'); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeRoots($q) { return $q->whereNull('parent_id'); }
    public function isSelectable(): bool { return $this->is_active && !$this->is_prohibited; }
}
