<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\View\PhpViewRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PhpViewRendererTest extends TestCase
{
    private const DIR = __DIR__.'/../fixtures/plain';

    public function test_renders_a_file_with_its_data(): void
    {
        $html = (new PhpViewRenderer)->file(self::DIR.'/invoices/show.html.php', ['doc' => 'rtl', 'name' => '<شركة>']);

        $this->assertSame("<p dir=\"rtl\">&lt;شركة&gt;</p>\n", $html);
    }

    public function test_finds_views_by_name_in_its_folders(): void
    {
        $renderer = new PhpViewRenderer(['/missing', self::DIR]);

        $this->assertStringContainsString('&lt;b&gt;', $renderer->view('invoices.show', ['doc' => 'ltr', 'name' => '<b>']));
        $this->assertStringContainsString('&lt;b&gt;', $renderer->view('invoices/show', ['doc' => 'ltr', 'name' => '<b>']));
    }

    public function test_view_names_cannot_leave_the_folders(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PhpViewRenderer([self::DIR]))->view('../plain/invoices/show');
    }

    public function test_missing_views_say_where_they_were_looked_for(): void
    {
        $this->expectExceptionMessage('View [nope] not found in: '.self::DIR);

        (new PhpViewRenderer([self::DIR]))->view('nope');
    }

    public function test_refuses_blade_views(): void
    {
        $this->expectExceptionMessage('is a Blade view, which needs Laravel');

        (new PhpViewRenderer)->file(__DIR__.'/../fixtures/greeting.blade.php');
    }

    public function test_a_failing_view_leaves_no_output_behind(): void
    {
        $level = ob_get_level();

        try {
            (new PhpViewRenderer)->file(self::DIR.'/broken.php');
            $this->fail('The view should throw.');
        } catch (RuntimeException $e) {
            $this->assertSame('broken view', $e->getMessage());
        }

        $this->assertSame($level, ob_get_level());
    }
}
