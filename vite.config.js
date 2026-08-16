import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Inertia + Vue 3 entry (imports resources/css/app.css itself).
                'resources/js/app.ts',
                // Legacy Blade entries — still referenced by resources/views/errors/*.blade.php
                // and welcome.blade.php while the Blade→Vue migration is in progress.
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    // Let Vite resolve asset URLs relative to the importing .vue file
                    // rather than rewriting them against the public path.
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            // Mirrors the "@/*" path in tsconfig.json so .vue files can import
            // "@/lib/utils", "@/components/ui/...", etc.
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});
