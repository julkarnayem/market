<?php
/*
 * Laravel Broadcasting Configuration
 *
 * Real-time messaging uses Laravel Broadcasting. Configure one of:
 *
 *   BROADCAST_DRIVER=reverb    (Laravel Reverb — self-hosted, recommended)
 *   BROADCAST_DRIVER=pusher    (Pusher Channels)
 *   BROADCAST_DRIVER=null      (polling fallback — no real-time)
 *
 * For Laravel Reverb (recommended for self-hosted):
 *   REVERB_APP_ID=
 *   REVERB_APP_KEY=
 *   REVERB_APP_SECRET=
 *   REVERB_HOST=localhost
 *   REVERB_PORT=8080
 *   REVERB_SCHEME=http
 *
 * For Pusher:
 *   PUSHER_APP_ID=
 *   PUSHER_APP_KEY=
 *   PUSHER_APP_SECRET=
 *   PUSHER_APP_CLUSTER=ap2
 *
 * Install Reverb: php artisan reverb:install
 * Start Reverb:  php artisan reverb:start
 *
 * NOTE: Real-time is NOT production-ready until one of the above is configured and tested.
 * The system falls back to polling (every 5 seconds) when BROADCAST_DRIVER=null.
 */

return [
    'default' => env('BROADCAST_DRIVER', 'null'),

    'connections' => [
        'reverb' => [
            'driver'   => 'reverb',
            'key'      => env('REVERB_APP_KEY'),
            'secret'   => env('REVERB_APP_SECRET'),
            'app_id'   => env('REVERB_APP_ID'),
            'options'  => [
                'host'   => env('REVERB_HOST', 'localhost'),
                'port'   => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME') === 'https',
            ],
        ],
        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'app_id'  => env('PUSHER_APP_ID'),
            'options' => [
                'cluster'  => env('PUSHER_APP_CLUSTER', 'ap2'),
                'useTLS'   => true,
            ],
        ],
        'null' => ['driver' => 'null'],
    ],
];
