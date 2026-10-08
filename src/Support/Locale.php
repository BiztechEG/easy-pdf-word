<?php

namespace BiztechEG\EasyPdfWord\Support;

/**
 * Locale names as documents accept them: ar, en, ar-EG, ar_SA, zh-Hant-TW.
 * Anything else (paths, markup) is refused, since the language picks the
 * template's lang/{language}.php file.
 */
final class Locale
{
    public static function isValid(string $locale): bool
    {
        return preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{1,8})*\z/', $locale) === 1;
    }

    /** "ar" for "ar_EG"; null when the locale is not valid. */
    public static function language(string $locale): ?string
    {
        return self::isValid($locale) ? strtolower(preg_split('/[-_]/', $locale)[0]) : null;
    }
}
