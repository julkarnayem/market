<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Source of truth for admin-configurable values. DB first, config fallback.
 * Money is poisha (int); rates are basis points (int). Always resolve business
 * values here rather than reading config directly in controllers/blades.
 */
class SettingsService
{
    private const CACHE_KEY = 'marketplace.settings';

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $this->cast($s->value, $s->type)])
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $this->encode($value), 'type' => $type, 'group' => $group]
        );
        Cache::forget(self::CACHE_KEY);
    }

    /** Drop an override so get()'s config/default fallback applies again. */
    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    /** Booleans are stored as '1'/'0' — a raw (string) false would persist ''. */
    private function encode(mixed $value): string
    {
        return match (true) {
            is_array($value) => (string) json_encode($value),
            is_bool($value)  => $value ? '1' : '0',
            default          => (string) $value,
        };
    }

    public function sellerFeeBp(): int
    {
        return (int) $this->get('seller_fee_bp', config('marketplace.fees.seller_fee_bp'));
    }

    public function buyerFeeEnabled(): bool
    {
        return (bool) $this->get('buyer_fee_enabled', config('marketplace.fees.buyer_fee_enabled'));
    }

    public function buyerFeeType(): string
    {
        return (string) $this->get('buyer_fee_type', config('marketplace.fees.buyer_fee_type'));
    }

    /** bp when type=percentage, poisha when type=fixed. */
    public function buyerFeeValue(): int
    {
        return (int) $this->get('buyer_fee_value', config('marketplace.fees.buyer_fee_value'));
    }

    public function minWithdrawal(): int
    {
        return (int) $this->get('minimum_withdrawal', config('marketplace.withdrawal.minimum'));
    }

    public function withdrawalFee(): int
    {
        return (int) $this->get('withdrawal_fee', config('marketplace.withdrawal.fee'));
    }

    public function offerValidityHours(): int
    {
        return (int) $this->get('offer_validity_hours', config('marketplace.offer.validity_hours'));
    }

    public function buyerProtectionHours(): int
    {
        return (int) $this->get('buyer_protection_hours', config('marketplace.order.buyer_protection_hours'));
    }

    public function earningLockHours(): int
    {
        return (int) $this->get('earning_lock_hours', config('marketplace.order.earning_lock_hours'));
    }

    /** [days => poisha] */
    public function promotionPrices(): array
    {
        return (array) $this->get('promotion_prices', config('marketplace.promotion_prices'));
    }

    private function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }
}
