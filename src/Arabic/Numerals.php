<?php

namespace BiztechEG\EasyPdfWord\Arabic;

use InvalidArgumentException;

class Numerals
{
    public const LATIN = 'latin';

    public const ARABIC = 'arabic';

    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /** Persian digits, converted to latin or arabic on input. */
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /**
     * Arabic-Indic digits. With $separators, a "." or "," between digits
     * becomes the Arabic decimal or thousands separator (١٢٬٥٠٠٫٧٥), since a
     * Latin point is hard to tell from ٠. Turn it off for fonts without them.
     */
    public static function toArabic(string|int|float $value, bool $separators = true): string
    {
        // E-mail addresses and links keep their digits: info@biz2tech.com, www.site2.com/p/3.
        $parts = preg_split('/([^\s@<>()]+@[^\s@<>()]+\.\w+|(?:https?:\/\/|www\.)[^\s<>]+)/iu', self::toLatin($value), -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                continue;
            }

            $part = str_replace(self::LATIN_DIGITS, self::ARABIC_DIGITS, $part);
            $parts[$i] = $separators
                ? preg_replace_callback('/(?<=[٠-٩])[.,](?=[٠-٩])/u', fn ($m) => $m[0] === '.' ? '٫' : '٬', $part) ?? $part
                : $part;
        }

        return implode('', $parts);
    }

    public static function toLatin(string|int|float $value): string
    {
        return str_replace(
            [...self::ARABIC_DIGITS, ...self::PERSIAN_DIGITS, '٫', '٬'],
            [...self::LATIN_DIGITS, ...self::LATIN_DIGITS, '.', ','],
            (string) $value
        );
    }

    public static function convert(string|int|float $value, string $style, bool $separators = true): string
    {
        return match (self::normalizeStyle($style)) {
            self::ARABIC => self::toArabic($value, $separators),
            default => self::toLatin($value),
        };
    }

    /**
     * Convert the digits in an HTML document's text only. Tags, attribute
     * values, <style> and <script> contents are left untouched so CSS sizes,
     * colours and URLs keep working.
     */
    public static function convertHtml(string $html, string $style, bool $separators = true): string
    {
        $style = self::normalizeStyle($style);

        $parts = preg_split(
            '/(<style\b[^>]*>.*?<\/style>|<script\b[^>]*>.*?<\/script>|<!--.*?-->|<[^>]+>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] === '<') {
                continue;
            }

            // Leave HTML entities such as &#123; alone.
            $parts[$i] = preg_replace_callback(
                '/&#?\w+;|[^&]+|&/u',
                fn ($m) => $m[0][0] === '&' && strlen($m[0]) > 1 ? $m[0] : self::convert($m[0], $style, $separators),
                $part
            );
        }

        return implode('', $parts);
    }

    public static function normalizeStyle(string $style): string
    {
        return match (strtolower($style)) {
            'arabic', 'arab', 'ar', 'eastern', 'hindi' => self::ARABIC,
            'latin', 'latn', 'en', 'western' => self::LATIN,
            default => throw new InvalidArgumentException("Unknown numerals style [{$style}]. Use \"latin\" or \"arabic\"."),
        };
    }
}
