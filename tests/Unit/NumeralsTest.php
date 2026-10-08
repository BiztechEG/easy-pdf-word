<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Arabic\Numerals;
use PHPUnit\Framework\TestCase;

class NumeralsTest extends TestCase
{
    public function test_converts_both_ways(): void
    {
        $this->assertSame('١٢٣٤٥٦٧٨٩٠', Numerals::toArabic('1234567890'));
        $this->assertSame('1234567890', Numerals::toLatin('١٢٣٤٥٦٧٨٩٠'));
        $this->assertSame('123', Numerals::toLatin('۱۲۳'));
    }

    public function test_html_conversion_only_touches_text(): void
    {
        $html = '<p style="font-size:12px" data-x="5">رقم 123 &#1633; &amp; 45</p><style>.a{width:10px}</style>';

        $this->assertSame(
            '<p style="font-size:12px" data-x="5">رقم ١٢٣ &#1633; &amp; ٤٥</p><style>.a{width:10px}</style>',
            Numerals::convertHtml($html, 'arabic')
        );
    }

    public function test_arabic_digits_use_the_arabic_separators(): void
    {
        $this->assertSame('١٢٬٥٠٠٫٧٥', Numerals::toArabic('12,500.75'));
        $this->assertSame('١٢,٥٠٠.٧٥', Numerals::toArabic('12,500.75', separators: false));
        $this->assertSame('القيمة: ١٢٬٥٠٠. شكراً, ٢٠٢٦/١٠/٠٨', Numerals::toArabic('القيمة: 12,500. شكراً, 2026/10/08'));
        $this->assertSame('12,500.75', Numerals::toLatin('١٢٬٥٠٠٫٧٥'));
    }
}
