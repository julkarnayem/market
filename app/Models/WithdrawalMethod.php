<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A payout method the admin controls.
 *
 * This replaces the old App\Enums\WithdrawalMethod. The *set* of methods is now
 * rows (add / rename / reorder / switch off from Admin → Settings), but the two
 * behaviours that used to live on the enum are still code, keyed on `type`,
 * because the withdrawals table only stores two field-shapes:
 *   - type 'mfs'  → a mobile-money wallet number (mfs_number)
 *   - type 'bank' → account name / number / bank / branch
 * A new method must pick one of those two shapes; that is the only constraint.
 */
class WithdrawalMethod extends Model
{
    public const TYPE_MFS  = 'mfs';
    public const TYPE_BANK = 'bank';

    protected $fillable = ['key', 'label', 'type', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** Request-lifetime memo of key => label, so labelling a page of rows is one query. */
    private static ?array $labelMap = null;

    /** Active methods, in display order — the set the withdrawal form may offer. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('key');
    }

    /** Mobile-money methods need a wallet number; a bank transfer needs the bank fields. */
    public function isMobileMoney(): bool
    {
        return $this->type !== self::TYPE_BANK;
    }

    /**
     * Stored in `withdrawals.method`. Mobile money keeps the historic 'mfs' value
     * (the specific provider lives in `mfs_provider` / `method_key`); a bank
     * transfer stores 'bank'.
     */
    public function storageKey(): string
    {
        return $this->isMobileMoney() ? self::TYPE_MFS : self::TYPE_BANK;
    }

    /**
     * The account fields this method requires, so validation and the form follow
     * one list.
     *
     * @return list<string>
     */
    public function requiredFields(): array
    {
        return $this->isMobileMoney()
            ? ['mfs_number']
            : ['bank_account_name', 'bank_account_number', 'bank_name'];
    }

    /**
     * Options for the user's withdrawal form — active only, in the shape the Vue
     * page already consumes: {value, label, is_bank}.
     *
     * @return list<array{value:string,label:string,is_bank:bool}>
     */
    public static function options(): array
    {
        return self::query()->active()->get()
            ->map(fn (self $m) => [
                'value'   => $m->key,
                'label'   => $m->label,
                'is_bank' => ! $m->isMobileMoney(),
            ])
            ->all();
    }

    /**
     * The label for a stored method key, whether or not the method is still
     * active — used to render historical withdrawals. Memoised to avoid an N+1
     * across a paginated list. Returns null if the key was deleted, so the caller
     * can fall back.
     */
    public static function labelFor(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        self::$labelMap ??= self::query()->pluck('label', 'key')->all();

        return self::$labelMap[$key] ?? null;
    }
}
