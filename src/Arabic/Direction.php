<?php

namespace BiztechEG\EasyPdfWord\Arabic;

class Direction
{
    public const RTL = 'rtl';

    public const LTR = 'ltr';

    /** Languages written right to left. */
    private const RTL_LANGUAGES = ['ar', 'arc', 'ckb', 'dv', 'fa', 'he', 'ku', 'ps', 'sd', 'ug', 'ur', 'yi'];

    public static function forLocale(?string $locale): string
    {
        if ($locale === null || $locale === '') {
            return self::LTR;
        }

        $language = strtolower(preg_split('/[-_@]/', $locale)[0]);

        return in_array($language, self::RTL_LANGUAGES, true) ? self::RTL : self::LTR;
    }

    public static function isRtlLocale(?string $locale): bool
    {
        return self::forLocale($locale) === self::RTL;
    }

    /**
     * Direction of a piece of text, decided by its first strong character
     * (the same rule as dir="auto").
     */
    public static function ofText(string $text, string $default = self::LTR): string
    {
        if (! preg_match('/[\p{Arabic}\p{Hebrew}\p{Syriac}\p{Thaana}]|\p{L}/u', $text, $m)) {
            return $default;
        }

        return preg_match('/[\p{Arabic}\p{Hebrew}\p{Syriac}\p{Thaana}]/u', $m[0]) ? self::RTL : self::LTR;
    }
}
