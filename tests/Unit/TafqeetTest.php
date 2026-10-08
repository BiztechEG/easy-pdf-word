<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Arabic\Tafqeet;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TafqeetTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            [0, 'صفر'],
            [1, 'واحد'],
            [11, 'أحد عشر'],
            [12, 'اثنا عشر'],
            [21, 'واحد وعشرون'],
            [105, 'مائة وخمسة'],
            [999, 'تسعمائة وتسعة وتسعون'],
            [1000, 'ألف'],
            [2000, 'ألفان'],
            [3000, 'ثلاثة آلاف'],
            [11000, 'أحد عشر ألفاً'],
            [123456, 'مائة وثلاثة وعشرون ألفاً وأربعمائة وستة وخمسون'],
            [2500000, 'مليونان وخمسمائة ألف'],
            [1000000000, 'مليار'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_numbers_in_words(int $number, string $words): void
    {
        $this->assertSame($words, Tafqeet::words($number));
    }

    public function test_feminine_numbers(): void
    {
        $this->assertSame('ثلاث', Tafqeet::words(3, Tafqeet::FEMININE));
        $this->assertSame('إحدى عشرة', Tafqeet::words(11, Tafqeet::FEMININE));
        $this->assertSame('اثنتان وعشرون', Tafqeet::words(22, Tafqeet::FEMININE));
    }

    public function test_decimals(): void
    {
        $this->assertSame('عشرة فاصلة خمسة', Tafqeet::words('10.50'));
        $this->assertSame('سالب سبعة', Tafqeet::words(-7));
    }

    public static function amounts(): array
    {
        return [
            [1250.5, 'EGP', 'ألف ومائتان وخمسون جنيهاً وخمسون قرشاً'],
            [1, 'EGP', 'جنيه واحد'],
            [2, 'SAR', 'ريالان'],
            [3.03, 'SAR', 'ثلاثة ريالات وثلاث هللات'],
            [15.11, 'SAR', 'خمسة عشر ريالاً وإحدى عشرة هللة'],
            [0.5, 'EGP', 'خمسون قرشاً'],
            [0, 'EGP', 'صفر جنيه'],
            [100, 'USD', 'مائة دولار'],
            [1234.567, 'KWD', 'ألف ومائتان وأربعة وثلاثون ديناراً وخمسمائة وسبعة وستون فلساً'],
            ['1,500.25', 'AED', 'ألف وخمسمائة درهم وخمسة وعشرون فلساً'],
            ['١٢٣٫٤٥', 'AED', 'مائة وثلاثة وعشرون درهماً وخمسة وأربعون فلساً'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_amounts(int|float|string $amount, string $currency, string $words): void
    {
        $this->assertSame($words, Tafqeet::amount($amount, $currency));
    }

    public function test_only_wrapper_for_invoices(): void
    {
        $this->assertSame('فقط مائة جنيه لا غير', Tafqeet::amount(100, 'EGP', true));
    }

    public function test_custom_currency(): void
    {
        Tafqeet::registerCurrency('JOD', [
            'main' => ['forms' => ['دينار', 'ديناران', 'دنانير', 'ديناراً']],
            'sub' => ['forms' => ['فلس', 'فلسان', 'فلوس', 'فلساً']],
            'subunits' => 1000,
        ]);

        $this->assertSame('خمسة دنانير', Tafqeet::amount(5, 'jod'));
    }

    public function test_unknown_currency_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Tafqeet::amount(5, 'XXX');
    }
}
