<?php

namespace BiztechEG\EasyPdfWord\Support;

/**
 * Colours from data and themes, checked before they reach CSS or Word, so
 * a value like "red; background: url(...)" cannot add styles or requests.
 */
final class Color
{
    /** #RGB, #RRGGBB, #RRGGBBAA, rgb()/rgba()/hsl() with numbers, or a name such as "red". */
    public static function isValid(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,20}|(rgb|hsl)a?\([\d\s.,%\/]+\))\z/i', $value) === 1;
    }

    public static function css(mixed $value, ?string $fallback = null): ?string
    {
        return self::isValid($value) ? $value : $fallback;
    }

    /**
     * RRGGBB for Word, from #RGB, #RRGGBB, #RRGGBBAA, rgb() or hsl() (the
     * alpha is dropped). Null for anything else, such as a colour name.
     */
    public static function hex(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $rgb = self::rgb(trim($value));

        return $rgb === null ? null : vsprintf('%02X%02X%02X', $rgb);
    }

    /** A colour mixed with white: tint('#0F766E', 0.9) is a pale teal for a background. */
    public static function tint(mixed $value, float $white, string $fallback = '#F3F4F6'): string
    {
        $rgb = is_string($value) ? self::rgb(trim($value)) : null;

        if ($rgb === null) {
            return $fallback;
        }

        $white = max(0.0, min(1.0, $white));

        return '#'.vsprintf('%02X%02X%02X', array_map(fn ($c) => (int) round($c + (255 - $c) * $white), $rgb));
    }

    /** @return array{0: int, 1: int, 2: int}|null */
    private static function rgb(string $value): ?array
    {
        if (preg_match('/^#?([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})\z/i', $value, $m)) {
            $hex = strlen($m[1]) <= 4 ? preg_replace('/(.)/', '$1$1', $m[1]) : $m[1];

            return array_map('hexdec', str_split(substr($hex, 0, 6), 2));
        }

        if (! preg_match('/^(rgb|hsl)a?\(([\d\s.,%\/]+)\)\z/i', $value, $m)) {
            return null;
        }

        $parts = preg_split('/[\s,\/]+/', trim($m[2]));

        if (count($parts) < 3) {
            return null;
        }

        $number = fn (string $part, float $max) => str_ends_with($part, '%') ? (float) $part / 100 * $max : (float) $part;

        if (strtolower($m[1]) === 'rgb') {
            return array_map(fn ($part) => (int) round(max(0, min(255, $number($part, 255)))), array_slice($parts, 0, 3));
        }

        $h = fmod((float) $parts[0], 360) / 360;
        $s = max(0, min(1, $number(str_ends_with($parts[1], '%') ? $parts[1] : $parts[1].'%', 1)));
        $l = max(0, min(1, $number(str_ends_with($parts[2], '%') ? $parts[2] : $parts[2].'%', 1)));
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $channel = function (float $t) use ($p, $q): int {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);
            $v = match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };

            return (int) round($v * 255);
        };

        return [$channel($h + 1 / 3), $channel($h), $channel($h - 1 / 3)];
    }
}
