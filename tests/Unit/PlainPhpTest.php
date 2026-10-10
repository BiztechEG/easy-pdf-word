<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Document;
use BiztechEG\EasyPdfWord\DocumentFactory;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;
use BiztechEG\EasyPdfWord\Output\File;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * The library without Laravel: no container, no facades, no config().
 */
class PlainPhpTest extends TestCase
{
    private static function docs(array $config = []): DocumentFactory
    {
        return EasyPdfWord::create(array_replace_recursive([
            'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => sys_get_temp_dir().'/easy-pdf-word-tests/mpdf']]],
            'templates' => ['paths' => [__DIR__.'/../fixtures/plain-templates']],
            'views' => ['paths' => [__DIR__.'/../fixtures/plain']],
        ], $config));
    }

    public function test_builds_documents_in_code_as_pdf_and_word(): void
    {
        $document = self::docs()->make()->heading('تقرير المبيعات')->paragraph('نص عربي')->table([['البند', 'القيمة'], ['أ', '10']])->locale('ar');

        $this->assertInstanceOf(Document::class, $document);

        $pdf = $document->pdf('تقرير');
        $this->assertInstanceOf(File::class, $pdf);
        $this->assertStringStartsWith('%PDF', $pdf->content());
        $this->assertSame('mpdf', $pdf->engine());
        $this->assertSame('تقرير.pdf', $pdf->filename());

        $word = $document->word();
        $this->assertStringStartsWith('PK', $word->content());
        $this->assertSame('document.docx', $word->filename());
    }

    public function test_wraps_html_in_the_page_layout(): void
    {
        $html = self::docs()->html('<p>مرحبا</p>')->locale('ar')->title('A & B')->toHtml();

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        $this->assertStringContainsString('<title>A &amp; B</title>', $html);
        $this->assertStringContainsString('<p>مرحبا</p>', $html);
    }

    public function test_renders_plain_php_templates_with_footer_and_labels(): void
    {
        $document = self::docs()->template('note', ['to' => 'أحمد', 'lines' => ['سطر <1>', 'سطر 2']])->locale('ar')->numerals('arabic');

        $html = $document->toHtml();
        $this->assertStringContainsString('<h1>إلى أحمد</h1>', $html);
        $this->assertStringContainsString('سطر &lt;١&gt;', $html);
        $this->assertStringContainsString('١,٢٣٤.٥٠ (٢)', $html);
        $this->assertStringContainsString('h1 { color: #0F766E; }', $html);
        $this->assertSame('<div style="text-align: center">صفحة {page} / {pages}</div>', trim($document->options()->footer));
        $this->assertStringStartsWith('%PDF', $document->pdf()->content());
    }

    public function test_reports_invalid_template_data(): void
    {
        try {
            self::docs()->template('note', ['lines' => []])->pdf();
            $this->fail('Invalid data should throw.');
        } catch (ValidationFailed $e) {
            $this->assertSame(['to', 'lines'], array_keys($e->errors()));
        }

        $this->expectException(ValidationFailed::class);
        $this->expectExceptionMessage('A line is forbidden.');

        self::docs()->template('note', ['to' => 'x', 'lines' => ['forbidden']])->pdf();
    }

    public function test_renders_views_by_name(): void
    {
        $html = self::docs()->view('greeting', ['name' => '<سارة>'])->locale('ar')->toHtml();

        $this->assertStringContainsString('<h1 dir="rtl">&lt;سارة&gt;</h1>', $html);
    }

    public function test_blade_templates_say_they_need_laravel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is a Blade view, which needs Laravel');

        $docs = self::docs();

        $docs->template('letter', $docs->templates()->get('letter')->sample())->toHtml();
    }

    public function test_zips_files(): void
    {
        $docs = self::docs();
        $zip = $docs->zip(['تقرير.pdf' => $docs->html('<p>1</p>')->pdf(), $docs->make()->paragraph('2')->word('two')]);
        $path = tempnam(sys_get_temp_dir(), 'zip-test');
        file_put_contents($path, $zip->content());
        $archive = new ZipArchive;
        $archive->open($path);

        $this->assertSame(['تقرير.pdf', 'two.docx'], [$archive->getNameIndex(0), $archive->getNameIndex(1)]);
        $this->assertSame('application/zip', $zip->mimeType());

        $archive->close();
        unlink($path);
    }

    public function test_settings_merge_over_the_defaults(): void
    {
        $options = self::docs(['pdf' => ['margins' => [10, 20]], 'theme' => ['company' => ['name' => 'شركة النور']]])->html('x')->options();

        $this->assertSame([10.0, 20.0, 10.0, 20.0], $options->margins);
        $this->assertSame('A4', $options->paper);
        $this->assertSame('شركة النور', $options->author);
        $this->assertSame('en', $options->locale);
        $this->assertSame('ltr', $options->direction);
    }
}
