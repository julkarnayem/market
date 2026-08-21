import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import animate from 'tailwindcss-animate';
import defaultTheme from 'tailwindcss/defaultTheme';

/**
 * A 50–900 scale whose every shade resolves through a CSS variable
 * (`--c-<token>-<shade>`, space-separated "R G B") instead of a build-time hex.
 * Defaults live in resources/css/app.css `:root`; App\Support\ThemeColors can
 * override them at runtime, so brand/mint/amber/rose are admin-editable with no
 * rebuild. `<alpha-value>` keeps opacity modifiers (`bg-brand-600/20`) working.
 */
const varScale = (token) =>
    Object.fromEntries(
        [50, 100, 200, 300, 400, 500, 600, 700, 800, 900].map((shade) => [
            shade,
            `rgb(var(--c-${token}-${shade}) / <alpha-value>)`,
        ]),
    );

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
                // The 4 admin-editable brand-semantic scales. Each shade resolves
                // through --c-<token>-<shade> (defaults in resources/css/app.css),
                // so recoloring the site is a settings change, not a rebuild:
                //   brand → indigo identity (buttons, links, active nav, focus rings)
                //   mint  → money (balances, payouts, "Paid", verified)
                //   amber → featured (promotion, urgency, warnings)
                //   rose  → danger (dispute, delete, errors); defaults to red #DC2626
                // Overriding the built-in `amber`/`rose` is intentional — in this app
                // every amber/rose utility already means Featured/Danger.
                brand: varScale('brand'),
                mint: varScale('mint'),
                amber: varScale('amber'),
                rose: varScale('rose'),

                // shadcn-vue semantic tokens, backed by the CSS variables in
                // resources/css/app.css. Additive: `brand`/`mint` above stay primary.
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                // Track the dynamic brand so shadcn focus rings follow admin recolor.
                ring: 'rgb(var(--c-brand-600) / <alpha-value>)',
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                primary: {
                    DEFAULT: 'rgb(var(--c-brand-600) / <alpha-value>)',
                    foreground: 'hsl(var(--primary-foreground))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                },
                destructive: {
                    DEFAULT: 'rgb(var(--c-rose-600) / <alpha-value>)',
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
    // typography backs the `prose` classes used by Pages/Legal.vue to render the
    // server-owned HTML bodies from PageController::pages().
    plugins: [forms, typography, animate],
};
