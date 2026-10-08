<?php

namespace BiztechEG\EasyPdfWord\Support;

use BiztechEG\EasyPdfWord\Arabic\Arabic;
use BiztechEG\EasyPdfWord\Arabic\Numerals;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

/**
 * Passed to every document view as $doc: direction, fonts, theme and
 * translated labels, so templates stay the same for Arabic and English.
 */
class DocContext
{
    public function __construct(
        public readonly string $locale,
        public readonly string $direction,
        public readonly string $font,
        public readonly array $theme,
        public readonly string $numerals,
        public readonly string $engine,
        public readonly string $fontCss = '',
        private readonly array $translations = [],
        private readonly array $fallbackTranslations = [],
    ) {}

    public function isRtl(): bool
    {
        return $this->direction === 'rtl';
    }

    /** "right" in RTL documents, "left" otherwise. */
    public function start(): string
    {
        return $this->isRtl() ? 'right' : 'left';
    }

    /** "left" in RTL documents, "right" otherwise. */
    public function end(): string
    {
        return $this->isRtl() ? 'left' : 'right';
    }

    public function theme(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->theme, $key, $default);
    }

    /** A label from the template's lang/{locale}.php, with :placeholders. */
    public function t(string $key, array $replace = []): string
    {
        $line = Arr::get($this->translations, $key) ?? Arr::get($this->fallbackTranslations, $key) ?? $key;

        foreach ($replace as $name => $value) {
            $line = str_replace(':'.$name, (string) $value, $line);
        }

        return $line;
    }

    /**
     * Keep a left-to-right value (phone, tax number, code, e-mail) in its
     * own order inside Arabic text, e.g. "+20 100 000 0000" or "123-456-789".
     */
    public function ltr(int|float|string|null $value): HtmlString
    {
        return new HtmlString('<bdo dir="ltr">'.e((string) $value).'</bdo>');
    }

    /** Format a number with thousands separators. Digits follow the document's numerals setting. */
    public function number(int|float|string|null $value, int $decimals = 2): HtmlString
    {
        $formatted = number_format((float) $value, $decimals);

        // Keeps the minus sign before the digits in RTL text ("-2.3", not "2.3-").
        return new HtmlString(str_starts_with($formatted, '-') ? '<bdo dir="ltr">'.$formatted.'</bdo>' : $formatted);
    }

    public function money(int|float|string|null $value, ?string $currency = null, int $decimals = 2): HtmlString
    {
        $amount = $this->number($value, $decimals)->toHtml();

        return new HtmlString($currency ? $amount.' '.e($currency) : $amount);
    }

    public function tafqeet(int|float|string $amount, string $currency, bool $only = true): string
    {
        return Arabic::tafqeet($amount, $currency, $only);
    }

    public function hijri(mixed $date = null, string $pattern = 'd MMMM y'): string
    {
        return Arabic::hijri($date, $pattern, Numerals::LATIN);
    }

    /**
     * An image as a data URI so every engine can show it, whether it is a
     * local path, a URL or already a data URI.
     */
    public function image(?string $source): ?string
    {
        if ($source === null || $source === '' || str_starts_with($source, 'data:') || preg_match('#^https?://#i', $source)) {
            return $source ?: null;
        }

        if (! is_file($source)) {
            return null;
        }

        $mime = mime_content_type($source) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($source));
    }

    public function usesCssFonts(): bool
    {
        return $this->fontCss !== '';
    }
}
