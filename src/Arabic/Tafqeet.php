<?php

namespace BiztechEG\EasyPdfWord\Arabic;

use InvalidArgumentException;

/**
 * Arabic number and amount to words (تفقيط).
 *
 *   Tafqeet::words(1250);              // ألف ومائتان وخمسون
 *   Tafqeet::amount(1250.5, 'EGP');    // ألف ومائتان وخمسون جنيهاً وخمسون قرشاً
 *   Tafqeet::amount(3, 'SAR', true);   // فقط ثلاثة ريالات لا غير
 */
class Tafqeet
{
    public const MASCULINE = 'm';

    public const FEMININE = 'f';

    private const ONES_M = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة'];

    private const ONES_F = ['', 'واحدة', 'اثنتان', 'ثلاث', 'أربع', 'خمس', 'ست', 'سبع', 'ثماني', 'تسع', 'عشر'];

    private const TENS = ['', 'عشرة', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    private const HUNDREDS = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    /** [singular, dual, plural (3-10), accusative (11-99)] */
    private const SCALES = [
        1_000_000_000_000 => ['تريليون', 'تريليونان', 'تريليونات', 'تريليوناً'],
        1_000_000_000 => ['مليار', 'ملياران', 'مليارات', 'ملياراً'],
        1_000_000 => ['مليون', 'مليونان', 'ملايين', 'مليوناً'],
        1_000 => ['ألف', 'ألفان', 'آلاف', 'ألفاً'],
    ];

    /**
     * Each unit: forms [singular, dual, plural (3-10), accusative (11-99)]
     * and gender. "subunits" is how many fractional units make one main unit.
     */
    private static array $currencies = [
        'EGP' => [
            'main' => ['forms' => ['جنيه', 'جنيهان', 'جنيهات', 'جنيهاً'], 'gender' => 'm'],
            'sub' => ['forms' => ['قرش', 'قرشان', 'قروش', 'قرشاً'], 'gender' => 'm'],
            'subunits' => 100,
        ],
        'SAR' => [
            'main' => ['forms' => ['ريال', 'ريالان', 'ريالات', 'ريالاً'], 'gender' => 'm'],
            'sub' => ['forms' => ['هللة', 'هللتان', 'هللات', 'هللة'], 'gender' => 'f'],
            'subunits' => 100,
        ],
        'AED' => [
            'main' => ['forms' => ['درهم', 'درهمان', 'دراهم', 'درهماً'], 'gender' => 'm'],
            'sub' => ['forms' => ['فلس', 'فلسان', 'فلوس', 'فلساً'], 'gender' => 'm'],
            'subunits' => 100,
        ],
        'QAR' => [
            'main' => ['forms' => ['ريال', 'ريالان', 'ريالات', 'ريالاً'], 'gender' => 'm'],
            'sub' => ['forms' => ['درهم', 'درهمان', 'دراهم', 'درهماً'], 'gender' => 'm'],
            'subunits' => 100,
        ],
        'KWD' => [
            'main' => ['forms' => ['دينار', 'ديناران', 'دنانير', 'ديناراً'], 'gender' => 'm'],
            'sub' => ['forms' => ['فلس', 'فلسان', 'فلوس', 'فلساً'], 'gender' => 'm'],
            'subunits' => 1000,
        ],
        'USD' => [
            'main' => ['forms' => ['دولار', 'دولاران', 'دولارات', 'دولاراً'], 'gender' => 'm'],
            'sub' => ['forms' => ['سنت', 'سنتان', 'سنتات', 'سنتاً'], 'gender' => 'm'],
            'subunits' => 100,
        ],
        'EUR' => [
            'main' => ['forms' => ['يورو', 'يورو', 'يورو', 'يورو'], 'gender' => 'm'],
            'sub' => ['forms' => ['سنت', 'سنتان', 'سنتات', 'سنتاً'], 'gender' => 'm'],
            'subunits' => 100,
        ],
    ];

    public static function registerCurrency(string $code, array $definition): void
    {
        foreach (['main', 'sub'] as $unit) {
            if (! isset($definition[$unit]['forms']) || count($definition[$unit]['forms']) !== 4) {
                throw new InvalidArgumentException("Currency [{$code}] needs four \"{$unit}.forms\": singular, dual, plural, accusative.");
            }
        }

        $definition['subunits'] ??= 100;
        $definition['main']['gender'] ??= self::MASCULINE;
        $definition['sub']['gender'] ??= self::MASCULINE;

        self::$currencies[strtoupper($code)] = $definition;
    }

    public static function currencies(): array
    {
        return array_keys(self::$currencies);
    }

    /**
     * The number in Arabic words. Decimals are read after "فاصلة".
     */
    public static function words(int|float|string $number, string $gender = self::MASCULINE): string
    {
        [$negative, $integer, $fraction] = self::split((string) $number);

        $text = self::integerWords($integer, $gender);

        if ($fraction !== '') {
            $text .= ' فاصلة '.self::integerWords((int) $fraction, $gender);
        }

        return $negative ? 'سالب '.$text : $text;
    }

    /**
     * An amount of money in words, e.g. "مائة وخمسون جنيهاً وخمسة وعشرون قرشاً".
     *
     * @param  bool  $only  wrap as "فقط ... لا غير", the usual form on invoices and cheques
     */
    public static function amount(int|float|string $amount, string $currency = 'EGP', bool $only = false): string
    {
        $code = strtoupper($currency);
        $definition = self::$currencies[$code]
            ?? throw new InvalidArgumentException("Unknown currency [{$currency}]. Register it with Tafqeet::registerCurrency().");

        $decimals = (int) round(log10($definition['subunits']));
        $normalized = number_format(abs((float) self::cleanNumber((string) $amount)), $decimals, '.', '');
        [, $integer, $fraction] = self::split($normalized);
        $fraction = (int) str_pad($fraction, $decimals, '0');
        $negative = str_starts_with(trim(self::cleanNumber((string) $amount)), '-') && ($integer > 0 || $fraction > 0);

        $parts = [];

        if ($integer > 0 || $fraction === 0) {
            $parts[] = self::counted($integer, $definition['main']);
        }

        if ($fraction > 0) {
            $parts[] = self::counted($fraction, $definition['sub']);
        }

        $text = implode(' و', $parts);

        if ($negative) {
            $text = 'سالب '.$text;
        }

        return $only ? 'فقط '.$text.' لا غير' : $text;
    }

    /**
     * A count followed by its noun in the right grammatical form.
     */
    private static function counted(int $count, array $unit): string
    {
        [$singular, $dual, $plural, $accusative] = $unit['forms'];
        $gender = $unit['gender'] ?? self::MASCULINE;

        if ($count === 0) {
            return 'صفر '.$singular;
        }

        if ($count === 1) {
            return $singular.' '.($gender === self::FEMININE ? self::ONES_F[1] : self::ONES_M[1]);
        }

        if ($count === 2) {
            return $dual;
        }

        return self::integerWords($count, $gender).' '.self::nounForm($count, $singular, $plural, $accusative);
    }

    private static function nounForm(int $count, string $singular, string $plural, string $accusative): string
    {
        $lastTwo = $count % 100;

        return match (true) {
            $lastTwo >= 3 && $lastTwo <= 10 => $plural,
            $lastTwo >= 11 && $lastTwo <= 99 => $accusative,
            default => $singular,
        };
    }

    private static function integerWords(int $number, string $gender): string
    {
        if ($number === 0) {
            return 'صفر';
        }

        $parts = [];

        foreach (self::SCALES as $scale => $forms) {
            $count = intdiv($number, $scale);
            $number %= $scale;

            if ($count === 0) {
                continue;
            }

            $parts[] = match (true) {
                $count === 1 => $forms[0],
                $count === 2 => $forms[1],
                default => self::belowThousand($count, self::MASCULINE).' '.self::nounForm($count, $forms[0], $forms[2], $forms[3]),
            };
        }

        if ($number > 0) {
            $parts[] = self::belowThousand($number, $gender);
        }

        return implode(' و', $parts);
    }

    private static function belowThousand(int $number, string $gender): string
    {
        $parts = [];
        $hundreds = intdiv($number, 100);
        $rest = $number % 100;

        if ($hundreds > 0) {
            $parts[] = self::HUNDREDS[$hundreds];
        }

        if ($rest > 0) {
            $parts[] = self::belowHundred($rest, $gender);
        }

        return implode(' و', $parts);
    }

    private static function belowHundred(int $number, string $gender): string
    {
        $feminine = $gender === self::FEMININE;
        $ones = $feminine ? self::ONES_F : self::ONES_M;

        if ($number <= 10) {
            return $ones[$number];
        }

        if ($number === 11) {
            return $feminine ? 'إحدى عشرة' : 'أحد عشر';
        }

        if ($number === 12) {
            return $feminine ? 'اثنتا عشرة' : 'اثنا عشر';
        }

        if ($number < 20) {
            return $ones[$number - 10].($feminine ? ' عشرة' : ' عشر');
        }

        $unit = $number % 10;
        $tens = self::TENS[intdiv($number, 10)];

        if ($unit === 0) {
            return $tens;
        }

        $unitWord = $unit === 1 ? ($feminine ? 'إحدى' : 'واحد') : $ones[$unit];

        return $unitWord.' و'.$tens;
    }

    /**
     * @return array{0: bool, 1: int, 2: string} negative, integer part, fraction digits
     */
    private static function split(string $number): array
    {
        $number = trim(self::cleanNumber($number));

        if (! preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', $number, $m)) {
            throw new InvalidArgumentException("[{$number}] is not a number.");
        }

        $fraction = rtrim($m[3] ?? '', '0');

        return [$m[1] === '-', (int) $m[2], $fraction];
    }

    private static function cleanNumber(string $number): string
    {
        return str_replace([',', '٬', '٫', ' '], ['', '', '.', ''], Numerals::toLatin($number));
    }
}
