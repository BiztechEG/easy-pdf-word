<?php

namespace BiztechEG\EasyPdfWord\Arabic;

use DateTimeInterface;

/**
 * One entry point for the Arabic helpers, usable anywhere in an app.
 */
class Arabic
{
    public static function tafqeet(int|float|string $amount, ?string $currency = null, bool $only = false): string
    {
        return $currency === null
            ? Tafqeet::words($amount)
            : Tafqeet::amount($amount, $currency, $only);
    }

    public static function hijri(
        DateTimeInterface|string|int|null $date = null,
        string $pattern = 'd MMMM y',
        string $numerals = Numerals::ARABIC,
    ): string {
        return Hijri::format($date, $pattern, $numerals);
    }

    public static function numerals(string|int|float $value, string $style = Numerals::ARABIC): string
    {
        return Numerals::convert($value, $style);
    }

    public static function direction(string $text): string
    {
        return Direction::ofText($text);
    }
}
