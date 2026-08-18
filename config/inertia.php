<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Page components
    |--------------------------------------------------------------------------
    |
    | This app keeps its page components in resources/js/Pages — capital P, which
    | is what resources/js/app.ts and ssr.ts glob for. inertia-laravel's default
    | is the lowercase resource_path('js/pages'), and the mismatch is invisible on
    | a case-insensitive filesystem: `assertInertia(...)->component(...)` resolves
    | the file on Windows and macOS, then fails on Linux with "Inertia page
    | component file [X] does not exist" for all 72 tests that assert a component.
    |
    | Only `inertia.testing.ensure_pages_exist` and the `inertia.view-finder`
    | binding read this, so a wrong value never reached production —
    | `pages.ensure_pages_exist` is false, and the browser resolves pages through
    | Vite's glob of the real directory.
    |
    | The whole block is spelled out because ServiceProvider::mergeConfigFrom is a
    | shallow top-level array_merge: declaring `pages` here replaces the package's
    | entire `pages` array, so omitting `extensions` would leave the view finder
    | with none. Values other than `paths` match the package defaults for v3.3.
    |
    */

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

];
