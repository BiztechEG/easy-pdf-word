<?php

use BiztechEG\EasyPdfWord\Arabic\Arabic;

if (! function_exists('tafqeet')) {
    /**
     * Number or amount in Arabic words: tafqeet(1250.5, 'EGP').
     */
    function tafqeet(int|float|string $amount, ?string $currency = null, bool $only = false): string
    {
        return Arabic::tafqeet($amount, $currency, $only);
    }
}

if (! function_exists('hijri_date')) {
    function hijri_date(DateTimeInterface|string|int|null $date = null, string $pattern = 'd MMMM y', string $numerals = 'arabic'): string
    {
        return Arabic::hijri($date, $pattern, $numerals);
    }
}

if (! function_exists('arabic_numerals')) {
    function arabic_numerals(string|int|float $value): string
    {
        return Arabic::numerals($value, 'arabic');
    }
}
