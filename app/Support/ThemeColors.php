<?php

namespace App\Support;

use App\Services\SettingsService;

/**
 * Runtime-editable theme colors.
 *
 * The 4 brand-semantic palettes (Brand / Money / Featured / Danger) are shipped
 * as CSS custom properties: their *default* channel values live in
 * resources/css/app.css `:root`, and tailwind.config.js points the `brand`,
 * `mint`, `amber` and `rose` scales at `rgb(var(--c-<token>-<shade>) / <alpha>)`.
 * That makes every `bg-brand-600`, `text-mint-700`, `ring-rose-500/20`, … resolve
 * through a variable instead of a build-time hex — so an admin can recolor the
 * whole site from Admin → Settings with no rebuild.
 *
 * The admin picks ONE hex per role; scale() generates the full 50–900 ramp from
 * it. Defaults are pixel-exact (hand-tuned Tailwind scales in app.css); the
 * generator only ever runs for a genuinely custom pick — SettingsController
 * forgets the setting when the chosen hex equals the default, so a no-op save
 * and a Reset both fall back to the exact default ramp.
 *
 * Role → Tailwind token (= CSS-var prefix `--c-<token>-`):
 *   brand → brand (indigo)   money → mint (green)
 *   featured → amber          danger → rose (defaults to Tailwind *red* #DC2626,
 *                                     the locked Danger color — not pink rose)
 */
class ThemeColors
{
    /** The representative hex an admin sees per role, and the "is this custom?" anchor. */
    public const DEFAULTS = [
        'brand'    => '#6366F1', // indigo-500
        'money'    => '#16A34A', // green-600
        'featured' => '#F59E0B', // amber-500
        'danger'   => '#DC2626', // red-600
    ];

    /** Role → the Tailwind color token / CSS-var prefix its utilities use. */
    private const TOKENS = [
        'brand'    => 'brand',
        'money'    => 'mint',
        'featured' => 'amber',
        'danger'   => 'rose',
    ];

    /**
     * Lightness ramp (50→900) applied to a custom pick's hue+saturation. Tuned to
     * resemble the default Tailwind scales: 600 (button bg) stays AA-safe on white,
     * 50 stays a light surface tint.
     */
    private const RAMP = [
        50 => 0.975, 100 => 0.94, 200 => 0.86, 300 => 0.76, 400 => 0.66,
        500 => 0.58, 600 => 0.50, 700 => 0.42, 800 => 0.34, 900 => 0.27,
    ];

    public function __construct(private SettingsService $settings) {}

    /** @return array<string,string> role => current hex (stored override, else default). */
    public function current(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $role => $default) {
            $out[$role] = (string) $this->settings->get("theme_{$role}", $default);
        }

        return $out;
    }

    /**
     * The `:root` override declarations for every role that has a stored custom
     * hex — e.g. "--c-brand-50: 238 242 255; --c-brand-100: …". Empty string when
     * nothing is customized, so the caller can skip emitting a <style> block.
     */
    public function overridesCss(): string
    {
        $decls = [];
        foreach (self::TOKENS as $role => $token) {
            $hex = $this->settings->get("theme_{$role}");
            if (! is_string($hex) || $hex === '') {
                continue; // not customized → app.css default ramp applies
            }
            foreach ($this->scale($hex) as $shade => $channels) {
                $decls[] = "--c-{$token}-{$shade}: {$channels};";
            }
        }

        return implode(' ', $decls);
    }

    /**
     * Generate a 50–900 scale from one hex: keep its hue + saturation, ramp the
     * lightness. Returns shade => "R G B" channel triplets (space-separated so
     * Tailwind's `rgb(var(--x) / <alpha-value>)` opacity modifiers work).
     *
     * @return array<int,string>
     */
    public function scale(string $hex): array
    {
        [$h, $s] = $this->hexToHsl($hex);

        $out = [];
        foreach (self::RAMP as $shade => $lightness) {
            [$r, $g, $b] = $this->hslToRgb($h, $s, $lightness);
            $out[$shade] = "{$r} {$g} {$b}";
        }

        return $out;
    }

    /** #RRGGBB → [h(0-1), s(0-1), l(0-1)]. */
    private function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d == 0.0) {
            return [0.0, 0.0, $l]; // achromatic
        }

        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => (($g - $b) / $d) + ($g < $b ? 6 : 0),
            $g => (($b - $r) / $d) + 2,
            default => (($r - $g) / $d) + 4,
        };

        return [$h / 6, $s, $l];
    }

    /** [h(0-1), s(0-1), l(0-1)] → [r,g,b] each 0-255. */
    private function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s == 0.0) {
            $v = (int) round($l * 255);

            return [$v, $v, $v];
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        return [
            (int) round($this->hue2rgb($p, $q, $h + 1 / 3) * 255),
            (int) round($this->hue2rgb($p, $q, $h) * 255),
            (int) round($this->hue2rgb($p, $q, $h - 1 / 3) * 255),
        ];
    }

    private function hue2rgb(float $p, float $q, float $t): float
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;

        return $p;
    }
}
