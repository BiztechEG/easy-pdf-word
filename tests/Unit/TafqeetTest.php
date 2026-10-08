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

    public function test_decimals_keep_leading_zeros(): void
    {
        $this->assertSame('واحد فاصلة صفر خمسة', Tafqeet::words(1.05));
        $this->assertSame('واحد فاصلة خمسة', Tafqeet::words(1.5));
        $this->assertSame('صفر فاصلة صفر صفر صفر صفر واحد', Tafqeet::words(1e-5));
    }

    public function test_thousands_ending_in_one_or_two(): void
    {
        $this->assertSame('مائة ألف وألف', Tafqeet::words(101000));
        $this->assertSame('مائة ألف وألفان', Tafqeet::words(102000));
        $this->assertSame('ثلاثمائة ألف وألف وخمسون', Tafqeet::words(301050));
    }

    public function test_duals_before_a_noun_drop_the_nun(): void
    {
        $this->assertSame('مائتان', Tafqeet::words(200));
        $this->assertSame('مائتا ألف', Tafqeet::words(200000));
        $this->assertSame('مائتا جنيه', Tafqeet::amount(200, 'EGP'));
        $this->assertSame('ألفا جنيه', Tafqeet::amount(2000, 'EGP'));
        $this->assertSame('ألف ومائتا ريال', Tafqeet::amount(1200, 'SAR'));
        $this->assertSame('مليونا جنيه', Tafqeet::amount(2000000, 'EGP'));
    }

    public function test_round_thousands_before_a_noun_drop_the_tanween(): void
    {
        $this->assertSame('خمسة وعشرون ألفاً', Tafqeet::words(25000));
        $this->assertSame('خمسة وعشرون ألف جنيه', Tafqeet::amount(25000, 'EGP'));
        $this->assertSame('أحد عشر ألف ريال', Tafqeet::amount(11000, 'SAR'));
        $this->assertSame('مائة وأحد عشر ألف ريال', Tafqeet::amount(111000, 'SAR'));
        $this->assertSame('خمسة عشر مليون جنيه', Tafqeet::amount(15000000, 'EGP'));
        $this->assertSame('خمسة وعشرون ألفاً وخمسمائة جنيه', Tafqeet::amount(25500, 'EGP'));
        $this->assertSame('فقط خمسة وعشرون ألف جنيه وخمسون قرشاً لا غير', Tafqeet::amount(25000.5, 'EGP', true));
    }

    public function test_invalid_and_too_large_numbers_throw(): void
    {
        foreach (['abc', '1000000000000000', 1e15] as $value) {
            try {
                Tafqeet::amount($value, 'EGP');
                $this->fail("No exception for {$value}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
