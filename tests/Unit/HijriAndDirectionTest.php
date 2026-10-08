<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Arabic\Direction;
use BiztechEG\EasyPdfWord\Arabic\Hijri;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

class HijriAndDirectionTest extends TestCase
{
    #[RequiresPhpExtension('intl')]
    public function test_hijri_date(): void
    {
        $this->assertSame('27 ربيع الآخر 1448 هـ', Hijri::format('2026-10-08', numerals: 'latin'));
        $this->assertSame('٢٧ ربيع الآخر ١٤٤٨ هـ', Hijri::format('2026-10-08'));
        $this->assertSame(['year' => 1448, 'month' => 4, 'day' => 27], Hijri::parts('2026-10-08'));
    }

    public function test_direction_from_locale(): void
    {
        $this->assertSame('rtl', Direction::forLocale('ar'));
        $this->assertSame('rtl', Direction::forLocale('ar_EG'));
        $this->assertSame('rtl', Direction::forLocale('fa-IR'));
        $this->assertSame('ltr', Direction::forLocale('en'));
        $this->assertSame('ltr', Direction::forLocale(null));
    }

    public function test_direction_from_text(): void
    {
        $this->assertSame('rtl', Direction::ofText('123 فاتورة Invoice'));
        $this->assertSame('ltr', Direction::ofText('Invoice فاتورة'));
        $this->assertSame('ltr', Direction::ofText('123'));
    }
}
