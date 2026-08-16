<?php
/*
 * BulkSMSBD.net SMS Gateway Configuration
 *
 * Set these in .env:
 *   BULKSMSBD_API_URL=http://bulksmsbd.net/api/smsapi
 *   BULKSMSBD_API_KEY=your_api_key_here
 *   BULKSMSBD_SENDER_ID=your_sender_id
 *   BULKSMSBD_ENABLED=true
 *
 * Official docs: https://bulksmsbd.net/api-documentation
 * The API uses a GET/POST request with these params:
 *   api_key, senderid, number, message
 * Response JSON: { "response_code": 202, "success_message": "..." }
 */
return [
    'api_url'   => env('BULKSMSBD_API_URL', 'http://bulksmsbd.net/api/smsapi'),
    'api_key'   => env('BULKSMSBD_API_KEY', ''),
    'sender_id' => env('BULKSMSBD_SENDER_ID', ''),
    'enabled'   => env('BULKSMSBD_ENABLED', false),
    'timeout'   => 15,
    'max_attempts' => 3,
];
