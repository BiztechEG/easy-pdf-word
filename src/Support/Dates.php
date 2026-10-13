<?php

namespace BiztechEG\EasyPdfWord\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;

/**
 * Dates without Laravel. now() follows Carbon's test clock when Carbon is
 * installed, so Laravel's travelTo() and Carbon::setTestNow() still apply.
 */
final class Dates
{
    public static function now(): DateTimeImmutable
    {
        if (class_exists(\Carbon\CarbonImmutable::class)) {
            return DateTimeImmutable::createFromInterface(\Carbon\CarbonImmutable::now());
        }

        return new DateTimeImmutable;
    }

    /**
     * A date from a DateTime, a date string ("2026-10-08", "2026/10/08 14:30")
     * or a Unix timestamp, in the default time zone unless $timezone is given.
     * Null and "" are now, as with Carbon::parse().
     */
    public static function parse(DateTimeInterface|string|int|null $date, ?string $timezone = null): DateTimeImmutable
    {
        if ($date === null || $date === '') {
            $now = self::now();

            return $timezone === null ? $now : $now->setTimezone(new DateTimeZone($timezone));
        }

        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date);
        }

        $zone = new DateTimeZone($timezone ?? date_default_timezone_get());

        if (is_int($date)) {
            return (new DateTimeImmutable('@'.$date))->setTimezone($zone);
        }

        return new DateTimeImmutable($date, $zone);
    }

    /**
     * A date in a pattern of the language files: "dd.MM.yyyy" is 08.10.2026.
     * The letters are the common ones of ICU (and of Java, .NET and Python's
     * Babel), so other versions of the library read the same patterns:
     * yyyy yy MM M dd d HH H mm ss. Text in 'quotes' is printed as it is.
     */
    public static function format(DateTimeInterface $date, string $pattern): string
    {
        $letters = ['yyyy' => 'Y', 'yy' => 'y', 'MM' => 'm', 'M' => 'n', 'dd' => 'd', 'd' => 'j', 'HH' => 'H', 'H' => 'G', 'mm' => 'i', 'ss' => 's'];

        return (string) preg_replace_callback(
            "/'([^']*)'|yyyy|yy|MM|M|dd|d|HH|H|mm|ss/",
            fn (array $m) => isset($m[1]) ? $m[1] : $date->format($letters[$m[0]]),
            $pattern,
        );
    }

    /**
     * The month's name in the language: "سبتمبر", "September", "Eylül".
     * Languages with a file in resources/lang have their names built in;
     * regions (ar_SY: "أيلول") and other languages need ext-intl, else the
     * English names.
     */
    public static function monthName(DateTimeInterface $date, string $locale): string
    {
        return self::name($date, $locale, 'months', (int) $date->format('n') - 1, 'LLLL');
    }

    /** The weekday's name in the language: "الخميس", "Thursday". */
    public static function dayName(DateTimeInterface $date, string $locale): string
    {
        return self::name($date, $locale, 'days', (int) $date->format('w'), 'cccc');
    }

    private static function name(DateTimeInterface $date, string $locale, string $key, int $index, string $pattern): string
    {
        $language = Locale::language($locale) ?? 'en';
        // A region has its own names: أيلول in ar_SY, جانفي in ar_DZ.
        $regional = strtolower(str_replace('-', '_', $locale)) !== $language;
        $names = Language::get($language, $key);

        if (Language::has($language) && ! ($regional && class_exists(IntlDateFormatter::class))) {
            return $names[$index];
        }

        if (class_exists(IntlDateFormatter::class)) {
            $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $date->getTimezone(), IntlDateFormatter::GREGORIAN, $pattern);
            $name = $formatter->format($date);

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return $names[$index];
    }
}
