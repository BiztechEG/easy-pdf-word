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

    /** RRGGBB for Word, or null for anything that is not a hex colour. */
    public static function hex(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})\z/i', $value, $m)) {
            return null;
        }

        $hex = strlen($m[1]) === 3 ? preg_replace('/(.)/', '$1$1', $m[1]) : $m[1];

        return strtoupper($hex);
    }
}
