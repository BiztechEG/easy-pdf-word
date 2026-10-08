<?php

namespace BiztechEG\EasyPdfWord\Support;

use BiztechEG\EasyPdfWord\Arabic\Tafqeet;

/**
 * Decimal places of a currency's amounts: 3 for the Kuwaiti dinar and its
 * neighbours (1000 fils), 0 for the yen, 2 for the rest.
 */
final class Currency
{
    /** ISO 4217 currencies whose minor unit is not 1/100. */
    private const DECIMALS = [
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
        'JPY' => 0, 'KRW' => 0,
    ];

    public static function decimals(?string $code): int
    {
        $code = strtoupper((string) $code);

        return Tafqeet::decimals($code) ?? self::DECIMALS[$code] ?? 2;
    }

    public static function round(int|float|string|null $amount, ?string $code): float
    {
        return round((float) $amount, self::decimals($code));
    }
}
