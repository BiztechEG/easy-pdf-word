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
        private readonly bool|array $remoteImages = false,
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

        // strtr() tries the longest name first, so :page does not eat the start of :pages.
        return strtr($line, collect($replace)->mapWithKeys(fn ($value, $name) => [':'.$name => (string) $value])->all());
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
        $formatted = number_format(self::toFloat($value), self::clampDecimals($decimals));

        // Keeps the minus sign before the digits in RTL text ("-2.3", not "2.3-").
        return new HtmlString(str_starts_with($formatted, '-') ? '<bdo dir="ltr">'.$formatted.'</bdo>' : $formatted);
    }

    /**
     * The same formatting as number(), as plain text for Word documents
     * (Word keeps the minus sign in place itself).
     */
    public function numberText(int|float|string|null $value, int $decimals = 2): string
    {
        return number_format(self::toFloat($value), self::clampDecimals($decimals));
    }

    /** Decimals can come from data (a report column), so a huge value must not build a huge string. */
    private static function clampDecimals(int $decimals): int
    {
        return max(0, min(10, $decimals));
    }

    /** "1,250.50" and "١٬٢٥٠٫٥٠" as 1250.5, not 1. */
    public static function toFloat(int|float|string|null $value): float
    {
        return is_string($value) ? (float) str_replace([',', ' '], '', Numerals::toLatin($value)) : (float) $value;
    }

    /** A rate or percentage with only the decimals it needs: 14, 2.5, 0.75. */
    public function rate(int|float|string|null $value): string
    {
        return rtrim(rtrim(number_format(self::toFloat($value), 2, '.', ''), '0'), '.');
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

        [$integer, $fraction, $per] = $this->split($amount, $currency);
        // -0.50 has no minus in its whole part, but is still negative.
        $minus = $integer === 0 && self::toFloat($amount) < 0 && $fraction > 0 ? 'سالب ' : '';
        $text = $minus.Tafqeet::words($integer).' '.strtoupper($currency).($fraction > 0 ? ' و'.$fraction.'/'.$per : '');

        return $only ? 'فقط '.$text.' لا غير' : $text;
    }

    /**
     * An amount in words in the document's language: Arabic tafqeet, or the
     * intl spell-out for other languages ("one thousand two hundred EGP").
     * Empty when neither is available.
     */
    public function inWords(int|float|string $amount, string $currency, bool $only = true): string
    {
        $language = strtolower(preg_split('/[-_]/', $this->locale)[0]);

        if ($language === 'ar') {
            return $this->tafqeet($amount, $currency, $only);
        }

        if (! class_exists(\NumberFormatter::class)) {
            return '';
        }

        [$integer, $fraction, $per] = $this->split($amount, $currency);
        $formatter = new \NumberFormatter($language, \NumberFormatter::SPELLOUT);
        $words = $formatter->format($integer);

        // -0.50: the language's word for minus ("minus", "moins"), as the whole part has none.
        if ($integer === 0 && self::toFloat($amount) < 0 && $fraction > 0) {
            $words = trim(str_replace($formatter->format(1), '', $formatter->format(-1))).' '.$words;
        }

        return trim($words.' '.strtoupper($currency).($fraction > 0 ? ' and '.$fraction.'/'.$per : '').($only ? ' only' : ''));
    }

    /** Decimal places of the currency's amounts: 3 for KWD, 2 for most. */
    public function decimals(?string $currency): int
    {
        return Currency::decimals($currency);
    }

    /**
     * An amount as whole units and minor units, e.g. [1, 125, 1000] for
     * 1.125 KWD.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function split(int|float|string $amount, string $currency): array
    {
        $decimals = Currency::decimals($currency);
        $value = round(self::toFloat($amount), $decimals);
        $per = 10 ** $decimals;

        return [(int) $value, (int) round(abs($value - (int) $value) * $per), $per];
    }

    /** A currency's short label from the template's "currencies" labels, or its code. */
    public function currency(string $code): string
    {
        $code = strtoupper($code);
        $label = $this->t('currencies.'.$code);

        if ($label !== 'currencies.'.$code) {
            return $label;
        }

        return $this->isRtl() ? (self::ARABIC_CURRENCIES[$code] ?? $code) : $code;
    }

    private const ARABIC_CURRENCIES = [
        'EGP' => 'ج.م', 'SAR' => 'ر.س', 'AED' => 'د.إ', 'KWD' => 'د.ك', 'QAR' => 'ر.ق',
        'BHD' => 'د.ب', 'OMR' => 'ر.ع', 'JOD' => 'د.أ', 'USD' => 'دولار', 'EUR' => 'يورو',
    ];

    /** Hijri dates need the intl extension; templates skip them without it. */
    public function hasHijri(): bool
    {
        return class_exists(\IntlDateFormatter::class);
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
     * passed on only when "images.remote" allows them (true, or their host).
     */
    public function image(?string $source): ?string
    {
        if ($source === null || $source === '' || str_contains($source, "\0")) {
            return null;
        }

        if (str_starts_with($source, 'data:')) {
            $svg = stripos($source, 'data:image/svg') === 0;

            return str_starts_with($source, 'data:image/') && (! $svg || self::isSafeSvg(self::dataUriContent($source))) ? $source : null;
        }

        if (preg_match('#^https?://#i', $source)) {
            return $this->allowsRemote($source) ? $source : null;
        }

        // Other schemes (phar://, ftp://, php://) and network shares are never read.
        if (preg_match('#^([a-z][a-z0-9+.-]+:|\\\\|//)#i', $source)) {
            return null;
        }

        $path = realpath($source);

        if ($path === false || ! is_file($path) || ! $this->isAllowedPath($path) || ! ($mime = $this->imageMime($path))) {
            return null;
        }

        $content = (string) file_get_contents($path);

        if ($mime === 'image/svg+xml' && ! self::isSafeSvg($content)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }

    /**
     * mPDF reads the files and URLs an SVG points to (<image href>, url(),
     * entities), outside the allowed folders and hosts. So an SVG is used
     * only when it refers to nothing but its own parts (href="#id").
     */
    private static function isSafeSvg(string $svg): bool
    {
        return mb_check_encoding($svg, 'UTF-8')
            && ! preg_match('/<!DOCTYPE|<!ENTITY|<\?xml-stylesheet|<(image|script|foreignObject|feImage)\b|@import|\b(href|src)\s*=\s*(?!["\']?\s*#)|url\(\s*(?!["\']?\s*#)/i', $svg);
    }

    /** The decoded content of a data URI, plain or base64. */
    private static function dataUriContent(string $uri): string
    {
        [$meta, $data] = explode(',', $uri, 2) + [1 => ''];

        return str_ends_with(strtolower($meta), ';base64') ? (string) base64_decode($data) : rawurldecode($data);
    }

    private function allowsRemote(string $url): bool
    {
        if (! is_array($this->remoteImages)) {
            return $this->remoteImages;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach ($this->remoteImages as $allowed) {
            $allowed = strtolower(trim((string) $allowed));

            if ($host !== '' && ($host === $allowed || (str_starts_with($allowed, '*.') && str_ends_with($host, substr($allowed, 1))))) {
                return true;
            }
        }

        return false;
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
