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
    private const MONTHS = [
        'ar' => ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    ];

    private const DAYS = [
        'ar' => ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
        'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    ];

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
     * The month's name in the language: "سبتمبر", "September". Arabic and
     * English are built in; other languages need ext-intl, else English.
     */
    public static function monthName(DateTimeInterface $date, string $locale): string
    {
        return self::name($date, $locale, self::MONTHS, (int) $date->format('n') - 1, 'LLLL');
    }

    /** The weekday's name in the language: "الخميس", "Thursday". */
    public static function dayName(DateTimeInterface $date, string $locale): string
    {
        return self::name($date, $locale, self::DAYS, (int) $date->format('w'), 'cccc');
    }

    private static function name(DateTimeInterface $date, string $locale, array $names, int $index, string $pattern): string
    {
        $language = Locale::language($locale) ?? 'en';

        if (isset($names[$language])) {
            return $names[$language][$index];
        }

        if (class_exists(IntlDateFormatter::class)) {
            $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $date->getTimezone(), IntlDateFormatter::GREGORIAN, $pattern);
            $name = $formatter->format($date);

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return $names['en'][$index];
    }
}
