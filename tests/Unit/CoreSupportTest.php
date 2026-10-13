<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Support\Data;
use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\Html;
use BiztechEG\EasyPdfWord\Support\HtmlString;
use BiztechEG\EasyPdfWord\Zatca\ZatcaQr;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class CoreSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_escape_is_htmlspecialchars_with_substitution(): void
    {
        $this->assertSame('&lt;b&gt;&quot;Tom&quot; &amp; &#039;Jerry&#039;&lt;/b&gt;', Html::escape('<b>"Tom" & \'Jerry\'</b>'));
        $this->assertSame('شركة &amp;amp; أخرى', Html::escape('شركة &amp; أخرى'));
        $this->assertSame('', Html::escape(''));
        $this->assertSame('', Html::escape(null));
        $this->assertSame('12.5', Html::escape(12.5));
        $this->assertSame("bad \u{FFFD}(", Html::escape("bad \xC3("));
        $this->assertSame('&amp;amp;', Html::escape('&amp;'));
        $this->assertSame('&amp;', Html::escape('&amp;', false));
        $this->assertSame('<bdo>x</bdo>', Html::escape(new HtmlString('<bdo>x</bdo>')));
        $this->assertSame('<bdo>x</bdo>', (string) new HtmlString('<bdo>x</bdo>'));
    }

    public function test_data_get_reads_dot_keys(): void
    {
        $data = ['company' => ['name' => 'بزنس تك', 'tax' => null], 'a.b' => 'literal', 0 => 'zero'];

        $this->assertSame('بزنس تك', Data::get($data, 'company.name'));
        $this->assertNull(Data::get($data, 'company.tax', 'default'));
        $this->assertSame('literal', Data::get($data, 'a.b'));
        $this->assertSame('zero', Data::get($data, 0));
        $this->assertSame('default', Data::get($data, 'company.name.more', 'default'));
        $this->assertSame('lazy', Data::get($data, 'missing', fn () => 'lazy'));
        $this->assertSame($data, Data::get($data, null));
    }

    public function test_dates_parse_like_carbon(): void
    {
        $this->assertSame('2026-10-08 00:00:00 UTC', Dates::parse('2026-10-08', 'UTC')->format('Y-m-d H:i:s T'));
        $this->assertSame('2026/10/08', Dates::parse('2026/10/08 14:30')->format('Y/m/d'));
        $this->assertSame('2026-10-08T09:00:00+00:00', Dates::parse(1791450000)->setTimezone(new DateTimeZone('UTC'))->format('c'));

        $cairo = new DateTimeImmutable('2026-10-08 23:30', new DateTimeZone('Africa/Cairo'));
        $this->assertSame($cairo->format('c'), Dates::parse($cairo)->format('c'));
    }

    public function test_now_follows_carbons_test_clock(): void
    {
        CarbonImmutable::setTestNow('2030-01-02 03:04:05');

        $this->assertSame('2030-01-02 03:04:05', Dates::now()->format('Y-m-d H:i:s'));
    }

    public function test_zatca_time_is_utc_whatever_the_input_zone(): void
    {
        $riyadh = new DateTimeImmutable('2026-10-08 10:00', new DateTimeZone('Asia/Riyadh'));

        $this->assertSame('2026-10-08T07:00:00Z', ZatcaQr::make('S', '3', $riyadh, 1, 1)->timestamp);
        $this->assertSame('2026-10-08T00:00:00Z', ZatcaQr::make('S', '3', '2026-10-08', 1, 1)->timestamp);
    }

    public function test_month_and_day_names_match_carbon(): void
    {
        foreach (['ar', 'ar_EG', 'ar_SA', 'ar_SY', 'ar_LB', 'ar_IQ', 'en', 'en_GB', 'fr', 'tr'] as $locale) {
            if (! in_array($locale, ['ar', 'ar_EG', 'ar_SA', 'en', 'en_GB'], true) && ! extension_loaded('intl')) {
                continue;
            }

            for ($day = 0; $day < 366; $day += 9) {
                $carbon = CarbonImmutable::create(2026, 1, 1)->addDays($day)->locale($locale);
                $date = new DateTimeImmutable($carbon->format('Y-m-d'));

                $this->assertSame($carbon->translatedFormat('F'), Dates::monthName($date, $locale), "{$locale} {$carbon->format('Y-m-d')}");
                $this->assertSame($carbon->translatedFormat('l'), Dates::dayName($date, $locale), "{$locale} {$carbon->format('Y-m-d')}");
            }
        }
    }
}
