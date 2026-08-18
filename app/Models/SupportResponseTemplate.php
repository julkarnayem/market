<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportResponseTemplate extends Model
{
    protected $fillable = ['title','category','body','is_active','created_by'];
    protected $casts    = ['is_active'=>'boolean'];

    /**
     * Placeholders render() will substitute; anything else is left verbatim.
     * Public so the admin form can document the real list instead of guessing.
     */
    public const VARIABLES = ['user_name','order_number','ticket_reference','listing_title','amount','status'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }

    /** Safely substitute known variables. */
    public function render(array $vars = []): string
    {
        $body = $this->body;
        foreach ($vars as $k => $v) {
            if (in_array($k, self::VARIABLES, true)) {
                $body = str_replace('{'.$k.'}', e($v), $body);
            }
        }
        return $body;
    }
}
