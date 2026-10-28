<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Builder\BlockText;
use BiztechEG\EasyPdfWord\Builder\DocumentBuilder as B;
use BiztechEG\EasyPdfWord\DocumentFactory;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use PHPUnit\Framework\TestCase;

/**
 * Document::blocks() and Document::text(): a Word document's content read
 * without rendering, for tests.
 */
class DocumentTextTest extends TestCase
{
    private static function docs(): DocumentFactory
    {
        return EasyPdfWord::create(['templates' => ['paths' => [__DIR__.'/../fixtures/plain-templates']]]);
    }

    public function test_blocks_of_a_document_built_in_code(): void
    {
        $blocks = self::docs()->make()->heading('عرض سعر')->paragraph('نص')->blocks();

        $this->assertSame(['heading', 'paragraph'], array_column($blocks, 'type'));
        $this->assertSame('عرض سعر', $blocks[0]['text']);
    }

    public function test_text_of_headings_paragraphs_tables_and_rich_text(): void
    {
        $text = self::docs()->make()->locale('ar')
            ->heading('عرض سعر رقم QT-1')
            ->paragraph([['text' => 'العميل: ', 'bold' => true], 'شركة النور'])
            ->table([
                ['البند', 'الكمية'],
                B::row(['حفر', ['lines' => ['120', ['text' => 'م3', 'bold' => true]]]], ['total' => true]),
                [['image' => '/x.png'], ['qr' => 'https://example.com']],
            ])
            ->image('/x.png')
            ->spacer()
            ->richText('<h2>الشروط</h2><p>يسري <b>30</b> يوماً.</p><ul><li>أول</li><li>ثانٍ</li></ul><script>x</script>')
            ->text();

        $this->assertSame(implode("\n", [
            'عرض سعر رقم QT-1',
            'العميل: شركة النور',
            "البند\tالكمية\nحفر\t120 م3\n\t",
            "الشروط\n\nيسري 30 يوماً.\n\nأول\n\nثانٍ",
        ]), $text);
    }

    public function test_lists_are_numbered_or_bulleted_by_depth(): void
    {
        $item = fn (string $text, array $items = []) => ['runs' => [['text' => $text]], 'items' => $items];

        $this->assertSame("1. أول\n2. ثانٍ\n  1. فرعي", BlockText::of([
            ['type' => 'list', 'items' => [$item('أول'), $item('ثانٍ', [$item('فرعي')])], 'options' => ['ordered' => true]],
        ]));
        $this->assertSame('• نقطة', BlockText::of([['type' => 'list', 'items' => [$item('نقطة')], 'options' => []]]));
    }

    public function test_header_and_footer_text_comes_first(): void
    {
        $text = self::docs()->make()
            ->header(fn (B $h) => $h->table([['شركة بيزتك', 'عرض سعر']]))
            ->footer('<p>صفحة {page} &amp; <b>تذييل</b></p><style>p{}</style>')
            ->firstPageHeader(fn (B $h) => $h->paragraph('ترويسة الصفحة الأولى'))
            ->paragraph('المتن')
            ->text();

        $this->assertSame("شركة بيزتك\tعرض سعر\nصفحة {page} & تذييل\nترويسة الصفحة الأولى\nالمتن", $text);
    }

    public function test_a_template_layout_runs_with_its_data_without_rendering(): void
    {
        $document = self::docs()->template('letterhead', ['company' => 'Biztech'])->locale('ar');

        $this->assertSame([['type' => 'paragraph', 'runs' => [['text' => 'Body of the letter']], 'style' => []]], $document->blocks());
        // The Word header layout wins over header.html.php, as in the Word file.
        $this->assertStringStartsWith('Layout header: Biztech ar', $document->text());
        $this->assertStringEndsWith('Body of the letter', $document->text());
    }

    public function test_html_and_view_documents_have_no_blocks(): void
    {
        $html = self::docs()->html('<p>مرحبا</p>');

        $this->assertSame([], $html->blocks());
        $this->assertSame('', $html->text());
        $this->assertSame([], self::docs()->template('note', ['to' => 'Amr', 'lines' => ['x']])->blocks());
    }
}
