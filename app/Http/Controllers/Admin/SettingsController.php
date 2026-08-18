<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Scalar settings this screen owns: key => [cast type, group].
     *
     * update() used to derive the type from PHP (`is_bool($value) ? 'bool' : 'int'`,
     * which never saw a bool because form input arrives as a string) and hardcoded
     * the group to 'fees' for every key — so one save moved minimum_withdrawal,
     * offer_validity_hours and the two order timers out of the groups the seeder
     * put them in. The mapping is explicit now.
     */
    private const SCALARS = [
        'seller_fee_bp'          => ['int',  'fees'],
        'buyer_fee_enabled'      => ['bool', 'fees'],
        'minimum_withdrawal'     => ['int',  'withdrawal'],
        'withdrawal_fee'         => ['int',  'withdrawal'],
        'offer_validity_hours'   => ['int',  'offers'],
        'earning_lock_hours'     => ['int',  'orders'],
        'buyer_protection_hours' => ['int',  'orders'],
    ];

    /** Purchasable promotion durations, in days. */
    private const PROMOTION_DAYS = [1, 2, 3, 4, 5];

    public function index(Request $request, SettingsService $settings)
    {
        // Reading is settings.view — the permission both sidebars gate the nav
        // item on. The Blade authorized settings.manage here, which made its own
        // "you do not have permission to manage settings" branch unreachable.
        $this->authorize('settings.view');

        // Typed accessors, not $settings->all(): all() returns the settings rows
        // verbatim, so a key missing from the table (nothing guarantees the
        // seeder ran) made the Blade's raw $settings['seller_fee_bp'] throw.
        // The accessors fall back to config/marketplace.php.
        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'seller_fee_bp'          => $settings->sellerFeeBp(),
                'buyer_fee_enabled'      => $settings->buyerFeeEnabled(),
                'minimum_withdrawal'     => $settings->minWithdrawal(),
                'withdrawal_fee'         => $settings->withdrawalFee(),
                'offer_validity_hours'   => $settings->offerValidityHours(),
                'earning_lock_hours'     => $settings->earningLockHours(),
                'buyer_protection_hours' => $settings->buyerProtectionHours(),
            ],
            // BDT, because that is the unit the promotion inputs edit; update()
            // converts back to poisha.
            'promotion_prices' => $this->promotionPrices($settings),
            // Mirrors the Blade's @can wrapper: settings.view alone gets a
            // read-only page. update() re-checks, so this only drives the UI.
            'can_manage' => $request->user()->can('settings.manage'),
        ]);
    }

    public function update(Request $request, SettingsService $settings)
    {
        $this->authorize('settings.manage');

        $rules = [
            'seller_fee_bp'          => 'required|integer|min:0|max:10000',
            'buyer_fee_enabled'      => 'required|boolean',
            'minimum_withdrawal'     => 'required|integer|min:100',
            'withdrawal_fee'         => 'required|integer|min:0',
            'offer_validity_hours'   => 'required|integer|min:1|max:168',
            'earning_lock_hours'     => 'required|integer|min:1',
            'buyer_protection_hours' => 'required|integer|min:1',
        ];

        // The promotion prices reached Money::toPoisha() with no rules at all, so
        // "abc" silently became ৳0 and a negative price was accepted.
        foreach (self::PROMOTION_DAYS as $day) {
            $rules["promotion_price_{$day}"] = 'required|numeric|min:0|max:1000000';
        }

        $data = $request->validate($rules);

        foreach (self::SCALARS as $key => [$type, $group]) {
            $settings->set($key, $data[$key], $type, $group);
        }

        $prices = [];
        foreach (self::PROMOTION_DAYS as $day) {
            $prices[$day] = Money::toPoisha($data["promotion_price_{$day}"]);
        }
        $settings->set('promotion_prices', $prices, 'json', 'promotion');

        return back()->with('success', 'Settings saved.');
    }

    /** @return list<array{days:int,label:string,field:string,bdt:float}> */
    private function promotionPrices(SettingsService $settings): array
    {
        $stored = $settings->promotionPrices();

        return array_map(fn (int $day) => [
            'days'  => $day,
            'label' => $day.' day'.($day > 1 ? 's' : ''),
            'field' => "promotion_price_{$day}",
            'bdt'   => Money::toBdt((int) ($stored[$day] ?? 0)),
        ], self::PROMOTION_DAYS);
    }
}
