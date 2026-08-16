<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(SettingsService $settings)
    {
        $this->authorize('settings.manage');
        return view('admin.settings', ['settings' => $settings->all()]);
    }

    public function update(Request $request, SettingsService $settings)
    {
        $this->authorize('settings.manage');
        $data = $request->validate([
            'seller_fee_bp' => 'required|integer|min:0|max:10000',
            'buyer_fee_enabled' => 'boolean',
            'minimum_withdrawal' => 'required|integer|min:100',
            'withdrawal_fee' => 'required|integer|min:0',
            'offer_validity_hours' => 'required|integer|min:1|max:168',
            'earning_lock_hours' => 'required|integer|min:1',
            'buyer_protection_hours' => 'required|integer|min:1',
        ]);

        foreach ($data as $key => $value) {
            $type = is_bool($value) ? 'bool' : 'int';
            $settings->set($key, $value, $type, 'fees');
        }

        // Promotion prices
        foreach ([1,2,3,4,5] as $day) {
            if ($request->has("promotion_price_{$day}")) {
                $poisha = Money::toPoisha($request->input("promotion_price_{$day}"));
                $prices[$day] = $poisha;
            }
        }
        if (!empty($prices)) {
            $settings->set('promotion_prices', $prices, 'json', 'promotion');
        }

        return back()->with('success', 'Settings saved.');
    }
}
