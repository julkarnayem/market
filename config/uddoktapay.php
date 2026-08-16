<?php
/*
 * UddoktaPay Payment Gateway Configuration
 *
 * Credentials MUST be stored in .env — never hardcoded.
 * Set these in your .env file:
 *
 *   UDDOKTAPAY_API_KEY=your_api_key_here
 *   UDDOKTAPAY_BASE_URL=https://sandbox.uddoktapay.com/api  (use live URL in production)
 *
 * Official UddoktaPay API docs: https://uddoktapay.com/docs
 * The service uses the standard UddoktaPay REST API endpoints:
 *   POST {base_url}/checkout-v2   — initiate payment
 *   POST {base_url}/verify-payment — verify after callback
 */
return [
    'api_key'  => env('UDDOKTAPAY_API_KEY', ''),
    'base_url' => env('UDDOKTAPAY_BASE_URL', 'https://sandbox.uddoktapay.com/api'),
    // Timeout in seconds for API calls
    'timeout'  => env('UDDOKTAPAY_TIMEOUT', 30),
];
