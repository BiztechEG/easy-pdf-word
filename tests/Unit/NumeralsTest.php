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

    public function test_emails_and_links_keep_their_digits(): void
    {
        $this->assertSame('راسلنا على info@biz2tech.com أو ٠١٠٠', Numerals::toArabic('راسلنا على info@biz2tech.com أو 0100'));
        $this->assertSame('الموقع https://site2.com/p/3 و www.x1.com رقم ٥', Numerals::toArabic('الموقع https://site2.com/p/3 و www.x1.com رقم 5'));
        $this->assertSame('<p>info@a1.com ١٢</p>', Numerals::convertHtml('<p>info@a1.com 12</p>', 'arabic'));
        $this->assertSame(
            '<p>https://pay.example.com/i?id=1024&amp;amount=150 رقم ٧</p>',
            Numerals::convertHtml('<p>https://pay.example.com/i?id=1024&amp;amount=150 رقم 7</p>', 'arabic')
        );
    }

    public function test_quoted_attribute_values_may_hold_a_closing_bracket(): void
    {
        $this->assertSame('<p title="a > 5">x ٥</p>', Numerals::convertHtml('<p title="a > 5">x 5</p>', 'arabic'));
        $this->assertSame('<p>١ &lt; ٢</p>', Numerals::convertHtml('<p>1 &lt; 2</p>', 'arabic'));
    }
}
