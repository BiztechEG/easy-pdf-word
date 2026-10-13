<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\DocumentFactory;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Templates\TemplateRegistry;
use PHPUnit\Framework\TestCase;

class TurkishTest extends TestCase
{
    private static function docs(): DocumentFactory
    {
        return EasyPdfWord::create([
            'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => sys_get_temp_dir().'/easy-pdf-word-tests/mpdf']]],
        ]);
    }

    private static function context(string $locale = 'tr'): DocContext
    {
        return new DocContext(locale: $locale, direction: 'ltr', font: 'cairo', theme: [], numerals: 'latin', engine: 'mpdf');
    }

    public function test_every_bundled_template_has_every_label_in_every_language(): void
    {
        $keys = function (array $labels, string $prefix = '') use (&$keys): array {
            $all = [];

            foreach ($labels as $key => $value) {
                $all = [...$all, ...(is_array($value) ? $keys($value, $prefix.$key.'.') : [$prefix.$key])];
            }

            return $all;
        };

        foreach (glob(TemplateRegistry::packagePath().'/*/template.php') as $manifest) {
            $name = basename(dirname($manifest));
            $template = self::docs()->templates()->get($name);
            $english = $keys($template->translations('en'));

            foreach ($template->locales() as $locale) {
                $this->assertFileExists(dirname($manifest)."/lang/{$locale}.json");
                // Arabic lists more copies and currencies than English needs.
                $this->assertSame([], array_diff($english, $keys($template->translations($locale))), "{$name} [{$locale}] lacks labels");
            }

            $this->assertContains('tr', $template->locales(), $name);
        }
    }

    public function test_dates_numbers_and_amounts_are_written_the_turkish_way(): void
    {
        $doc = self::context('tr_TR');

        $this->assertSame('08.10.2026', $doc->date('2026-10-08'));
        $this->assertSame('08.10.2026 14:30', $doc->date('2026-10-08 14:30', true));
        $this->assertSame('1.250.000,50', (string) $doc->number(1250000.5));
        $this->assertSame('-1.250,50', $doc->numberText(-1250.5));
        $this->assertSame('2,5', $doc->rate(2.5));
        $this->assertSame('Ekim', $doc->monthName('2026-10-08'));
        $this->assertSame('Perşembe', $doc->dayName('2026-10-08'));
        $this->assertSame('TL', $doc->currency('try'));
        $this->assertSame('EUR', $doc->currency('EUR'));
        $this->assertSame('a, b', $doc->join(['a', 'b']));
        $this->assertSame('İkinci', $doc->ucfirst('ikinci'));
        $this->assertSame('Üçüncü', $doc->ucfirst('üçüncü'));
        $this->assertSame('Ikinci', self::context('en')->ucfirst('ikinci'));

        if (class_exists(\NumberFormatter::class)) {
            $this->assertSame('Yalnız bin iki yüz elli Türk Lirası ve 50/100', $doc->inWords(1250.5, 'TRY'));
            $this->assertSame('on beş ABD Doları', $doc->inWords(15, 'usd', false));
            $this->assertSame('Yalnız yüz CHF', $doc->inWords(100, 'CHF'));
        }
    }

    public function test_english_and_arabic_keep_their_formats(): void
    {
        $this->assertSame('2026/10/08', self::context('en')->date('2026-10-08'));
        $this->assertSame('1,250.50', self::context('ar')->numberText(1250.5));
        $this->assertSame('ج.م', self::context('ar')->currency('EGP'));
        $this->assertSame('EGP', self::context('en')->currency('EGP'));
        $this->assertSame('أ، ب', self::context('ar')->join(['أ', 'ب']));

        if (class_exists(\NumberFormatter::class)) {
            $this->assertSame('one thousand two hundred fifty EGP and 50/100 only', self::context('en')->inWords(1250.5, 'EGP'));
        }
    }

    public function test_the_bundled_templates_render_in_turkish(): void
    {
        $docs = self::docs();
        $invoice = $docs->template('invoice', $docs->templates()->get('invoice')->sample())->locale('tr')->toHtml();

        $this->assertStringContainsString('<html lang="tr" dir="ltr">', $invoice);
        $this->assertStringContainsString('Fatura No.', $invoice);
        $this->assertStringContainsString('KDV (%14)', $invoice);
        $this->assertStringContainsString('25.000,00', $invoice);
        $this->assertStringContainsString('08.10.2026', $invoice);

        $contract = $docs->template('contract', $docs->templates()->get('contract')->sample())->locale('tr')->toHtml();
        $this->assertStringContainsString('İkinci Taraf', $contract);
        $this->assertStringContainsString('Madde 1', $contract);

        $this->assertStringStartsWith('%PDF', $docs->template('receipt', $docs->templates()->get('receipt')->sample())->locale('tr')->pdf()->content());
        $this->assertStringStartsWith('PK', $docs->template('payslip', $docs->templates()->get('payslip')->sample())->locale('tr')->word()->content());
    }
}
