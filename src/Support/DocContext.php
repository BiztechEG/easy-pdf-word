<?php

namespace BiztechEG\EasyPdfWord\Support;

use BiztechEG\EasyPdfWord\Arabic\Arabic;
use BiztechEG\EasyPdfWord\Arabic\Numerals;
use BiztechEG\EasyPdfWord\Arabic\Tafqeet;
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
        private readonly ?array $imagePaths = null,
        private readonly bool $remoteImages = true,
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

    /** All labels for the document's language, falling back to English. */
    public function translations(): array
    {
        return array_replace_recursive($this->fallbackTranslations, $this->translations);
    }

    /**
     * Keep a left-to-right value (phone, tax number, code, e-mail) in its
     * own order inside Arabic text, e.g. "+20 100 000 0000" or "123-456-789".
     */
    public function ltr(int|float|string|null $value): HtmlString
    {
        return new HtmlString('<bdo dir="ltr">'.e((string) $value).'</bdo>');
    }

    /**
     * Any value as display text: dates as Y/m/d, enums by value, booleans
     * as ✓, arrays and objects as JSON.
     */
    public function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '✓' : '',
            $value instanceof \DateTimeInterface => $value->format('Y/m/d'),
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \UnitEnum => $value->name,
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }

    /** Format a number with thousands separators. Digits follow the document's numerals setting. */
    public function number(int|float|string|null $value, int $decimals = 2): HtmlString
    {
        $formatted = number_format((float) $value, $decimals);

        // Keeps the minus sign before the digits in RTL text ("-2.3", not "2.3-").
        return new HtmlString(str_starts_with($formatted, '-') ? '<bdo dir="ltr">'.$formatted.'</bdo>' : $formatted);
    }

    /**
     * The same formatting as number(), as plain text for Word documents
     * (Word keeps the minus sign in place itself).
     */
    public function numberText(int|float|string|null $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals);
    }

    public function money(int|float|string|null $value, ?string $currency = null, int $decimals = 2): HtmlString
    {
        $amount = $this->number($value, $decimals)->toHtml();

        return new HtmlString($currency ? $amount.' '.e($currency) : $amount);
    }

    /**
     * The amount in Arabic words. For a currency Tafqeet does not know, the
     * number is read in words followed by the code and the cents as a
     * fraction: "فقط مائة وخمسون GBP و25/100 لا غير".
     */
    public function tafqeet(int|float|string $amount, string $currency, bool $only = true): string
    {
        if (in_array(strtoupper($currency), Tafqeet::currencies(), true)) {
            return Arabic::tafqeet($amount, $currency, $only);
        }

        $value = round((float) Numerals::toLatin(str_replace(',', '', (string) $amount)), 2);
        $cents = (int) round(abs($value - (int) $value) * 100);
        $text = Tafqeet::words((int) $value).' '.strtoupper($currency).($cents > 0 ? ' و'.$cents.'/100' : '');

        return $only ? 'فقط '.$text.' لا غير' : $text;
    }

    public function hijri(mixed $date = null, string $pattern = 'd MMMM y'): string
    {
        return Arabic::hijri($date, $pattern, Numerals::LATIN);
    }

    /**
     * An image as a data URI so every engine can show it, whether it is a
     * local path, a URL or already a data URI.
     *
     * Only image files inside the allowed folders (config "images.paths")
     * are read, so a path in user data cannot pull in other files. URLs are
     * passed on unless "images.remote" is off.
     */
    public function image(?string $source): ?string
    {
        if ($source === null || $source === '') {
            return null;
        }

        if (str_starts_with($source, 'data:')) {
            return str_starts_with($source, 'data:image/') ? $source : null;
        }

        if (preg_match('#^https?://#i', $source)) {
            return $this->remoteImages ? $source : null;
        }

        $path = realpath($source);

        if ($path === false || ! is_file($path) || ! $this->isAllowedPath($path) || ! ($mime = $this->imageMime($path))) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    private function isAllowedPath(string $path): bool
    {
        if ($this->imagePaths === null) {
            return true;
        }

        foreach ($this->imagePaths as $base) {
            $base = realpath((string) $base);

            if ($base !== false && str_starts_with($path, rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function imageMime(string $path): ?string
    {
        $info = @getimagesize($path);

        if ($info !== false && str_starts_with($info['mime'], 'image/')) {
            return $info['mime'];
        }

        $head = (string) file_get_contents($path, false, null, 0, 512);

        return str_ends_with(strtolower($path), '.svg') && preg_match('/<svg[\s>]/i', $head) ? 'image/svg+xml' : null;
    }

    public function usesCssFonts(): bool
    {
        return $this->fontCss !== '';
    }
}
