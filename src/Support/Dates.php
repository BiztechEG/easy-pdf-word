<?php

namespace BiztechEG\EasyPdfWord\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

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
     */
    public static function parse(DateTimeInterface|string|int $date, ?string $timezone = null): DateTimeImmutable
    {
        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date);
        }

        $zone = new DateTimeZone($timezone ?? date_default_timezone_get());

        if (is_int($date)) {
            return (new DateTimeImmutable('@'.$date))->setTimezone($zone);
        }

        return new DateTimeImmutable($date, $zone);
    }
}
