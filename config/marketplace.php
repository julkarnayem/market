<?php

/*
| Marketplace structural defaults (FALLBACK for App\Services\SettingsService).
| MONEY is integer poisha (1 BDT = 100 poisha). RATES are integer basis points
| (10% = 1000 bp). Never store money as float. Resolve business values through
| SettingsService, not directly from config, so admin changes take effect.
*/

return [
    'currency' => 'BDT',
    'currency_symbol' => '৳',
    'subunits' => 100,

    'fees' => [
        'seller_fee_bp' => 1000,          // 10% — applies to ALL prices, no free threshold
        'buyer_fee_enabled' => false,     // OFF by default
        'buyer_fee_type' => 'percentage', // percentage (value = bp) | fixed (value = poisha)
        'buyer_fee_value' => 0,
    ],

    'withdrawal' => [
        'minimum' => 5000,   // ৳50
        'fee' => 500,        // ৳5 fixed
        'method' => 'mfs',
    ],

    'offer' => [
        'validity_hours' => 8,
    ],

    'order' => [
        'buyer_protection_hours' => 72,   // auto-complete window
        'earning_lock_hours' => 8,        // lock after Order Complete
    ],

    'listing' => [
        'fee' => 0,                       // free
    ],

    // days => price in poisha
    'promotion_prices' => [
        1 => 5000,   // ৳50
        2 => 10000,  // ৳100
        3 => 15000,  // ৳150
        4 => 20000,  // ৳200
        5 => 25000,  // ৳250
    ],

    'sms' => [
        'provider' => 'bulksmsbd',
        'enabled' => false,
    ],
];
