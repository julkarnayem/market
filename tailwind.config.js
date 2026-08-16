import forms from '@tailwindcss/forms';
import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.{vue,ts}',
        './app/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                display: ['Sora', ...defaultTheme.fontFamily.sans],
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Primary — emerald brand. 500 = #10B981 (primary), 600 = #059669 (dark), 50 = #ECFDF5 (light).
                // Repointed from indigo so existing .btn-primary/.badge-brand/.nav-link-active/focus rings
                // become emerald with no per-view edits during the Blade→Vue coexistence phase.
                brand: {
                    50: '#ECFDF5', 100: '#D1FAE5', 200: '#A7F3D0', 300: '#6EE7B7',
                    400: '#34D399', 500: '#10B981', 600: '#059669', 700: '#047857',
                    800: '#065F46', 900: '#064E3B',
                },
                // Money / earnings / verified accents.
                mint: {
                    50: '#ECFDF7', 100: '#D1FAEC', 200: '#A7F3D9', 300: '#6EE7C3',
                    400: '#34D3AA', 500: '#10B98F', 600: '#059B79', 700: '#047A61',
                    800: '#065F4E', 900: '#064E40',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 4px 16px -4px rgb(15 23 42 / 0.08)',
                pop: '0 8px 32px -8px rgb(15 23 42 / 0.18)',
            },
            borderRadius: {
                xl: '0.875rem',
                '2xl': '1.25rem',
            },
        },
    },
    plugins: [forms],
};
