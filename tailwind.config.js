import forms from '@tailwindcss/forms';
import animate from 'tailwindcss-animate';
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

                // shadcn-vue semantic tokens, backed by the CSS variables in
                // resources/css/app.css. Additive: `brand`/`mint` above stay primary.
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },
            },
            keyframes: {
                'accordion-down': {
                    from: { height: '0' },
                    to: { height: 'var(--radix-accordion-content-height)' },
                },
                'accordion-up': {
                    from: { height: 'var(--radix-accordion-content-height)' },
                    to: { height: '0' },
                },
            },
            animation: {
                'accordion-down': 'accordion-down 0.2s ease-out',
                'accordion-up': 'accordion-up 0.2s ease-out',
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
    plugins: [forms, animate],
};
