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

    public function test_escape_matches_laravel(): void
    {
        foreach (['<b>"Tom" & \'Jerry\'</b>', 'شركة &amp; أخرى', '', null, 12.5, "bad \xC3("] as $value) {
            $this->assertSame(e($value), Html::escape($value), var_export($value, true));
        }

        $this->assertSame('&amp;amp;', Html::escape('&amp;'));
        $this->assertSame('&amp;', Html::escape('&amp;', false));
        $this->assertSame('<bdo>x</bdo>', Html::escape(new HtmlString('<bdo>x</bdo>')));
    }

    public function test_html_string_is_raw_in_blade(): void
    {
        $html = new HtmlString('<bdo dir="ltr">+20 100</bdo>');

        $this->assertInstanceOf(\Illuminate\Contracts\Support\Htmlable::class, $html);
        $this->assertInstanceOf(\Illuminate\Support\HtmlString::class, $html);
        $this->assertSame('<bdo dir="ltr">+20 100</bdo>', e($html));
        $this->assertSame('<bdo dir="ltr">+20 100</bdo>', (string) $html);
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
}
