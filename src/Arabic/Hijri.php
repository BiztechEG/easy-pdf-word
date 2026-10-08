<?php

namespace BiztechEG\EasyPdfWord\Arabic;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use IntlDateFormatter;
use RuntimeException;

/**
 * Hijri (Umm al-Qura) dates through the intl extension.
 *
 *   Hijri::format('2026-10-08');                 // ٢٦ ربيع الآخر ١٤٤٨ هـ
 *   Hijri::format('2026-10-08', numerals: 'latin'); // 26 ربيع الآخر 1448 هـ
 */
class Hijri
{
    public static function format(
        DateTimeInterface|string|int|null $date = null,
        string $pattern = 'd MMMM y',
        string $numerals = Numerals::ARABIC,
        bool $suffix = true,
    ): string {
        if (! class_exists(IntlDateFormatter::class)) {
            throw new RuntimeException('Hijri dates need the PHP intl extension.');
        }

        $date = match (true) {
            $date instanceof DateTimeInterface => $date,
            $date === null => Carbon::now(),
            default => Carbon::parse($date),
        };

        $formatter = new IntlDateFormatter(
            'ar@calendar=islamic-umalqura;numbers=latn',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $date->getTimezone(),
            IntlDateFormatter::TRADITIONAL,
            $pattern,
        );

        $text = $formatter->format($date);

        if ($text === false) {
            throw new RuntimeException('Could not format the Hijri date: '.$formatter->getErrorMessage());
        }

        $text = Numerals::convert($text, $numerals);

        return $suffix ? $text.' هـ' : $text;
    }

    /**
     * @return array{year: int, month: int, day: int}
     */
    public static function parts(DateTimeInterface|string|int|null $date = null): array
    {
        [$year, $month, $day] = array_map('intval', explode('-', self::format($date, 'y-M-d', Numerals::LATIN, false)));

        return compact('year', 'month', 'day');
    }
}
