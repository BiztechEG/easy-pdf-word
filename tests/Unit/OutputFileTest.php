<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Output\File;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class OutputFileTest extends TestCase
{
    public function test_renders_once_on_first_use(): void
    {
        $calls = 0;
        $file = File::pdf(function () use (&$calls) {
            $calls++;

            return ['%PDF-1.4', 'mpdf'];
        }, 'فاتورة');

        $this->assertSame(0, $calls);
        $this->assertSame('mpdf', $file->engine());
        $this->assertSame('%PDF-1.4', $file->content());
        $this->assertSame(base64_encode('%PDF-1.4'), $file->base64());
        $this->assertSame(1, $calls);
        $this->assertSame('application/pdf', $file->mimeType());
        $this->assertSame('فاتورة.pdf', $file->filename());
        $this->assertSame('a-b.DOCX', File::word(fn () => ['', 'phpword'], 'a/b.DOCX')->filename());
    }

    public function test_saves_to_a_new_folder(): void
    {
        $dir = sys_get_temp_dir().'/easy-pdf-word-'.bin2hex(random_bytes(4));
        $path = File::pdf(fn () => ['bytes', 'mpdf'])->save($dir.'/nested/a.pdf');

        try {
            $this->assertSame('bytes', file_get_contents($path));
        } finally {
            @unlink($path);
            @rmdir($dir.'/nested');
            @rmdir($dir);
        }
    }

    public function test_send_writes_the_bytes(): void
    {
        ob_start();
        File::pdf(fn () => ['bytes', 'mpdf'])->send('x.pdf', inline: true);

        $this->assertSame('bytes', ob_get_clean());
    }

    public function test_send_drops_output_waiting_in_a_buffer(): void
    {
        ob_start();
        ob_start();
        echo "\n";
        File::pdf(fn () => ['bytes', 'mpdf'])->send('x.pdf');

        $this->assertSame('bytes', ob_get_clean());
    }

    public function test_download_name_has_a_latin_fallback(): void
    {
        $file = new class(fn () => ['bytes', 'mpdf'], 'document.pdf', 'application/pdf', 'pdf') extends File
        {
            public function header(string $name): string
            {
                return $this->disposition('attachment', $name);
            }
        };

        $header = $file->header('فاتورة-1024.pdf');

        $this->assertStringEndsWith("filename*=utf-8''".rawurlencode('فاتورة-1024.pdf'), $header);
        $this->assertStringNotContainsString('?', $header);
        $this->assertStringStartsWith(
            function_exists('transliterator_transliterate') ? 'attachment; filename="fatwrt-1024.pdf"' : 'attachment; filename="______-1024.pdf"',
            $header,
        );
        $this->assertSame('attachment; filename="invoice.pdf"', $file->header('invoice.pdf'));
    }

    public function test_archive_keeps_names_unique_and_flat(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip is missing.');
        }

        $pdf = fn (string $name) => File::pdf(fn () => [$name, 'mpdf'], $name);
        $bytes = File::archive([$pdf('invoice'), $pdf('Invoice.pdf'), '../../evil' => $pdf('x'), "a\0b" => $pdf('y')]);
        $path = tempnam(sys_get_temp_dir(), 'zip-test');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        $zip->open($path);
        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $zip->close();
        unlink($path);

        $this->assertSame(['invoice.pdf', 'Invoice (2).pdf', '-..-evil.pdf', 'ab.pdf'], $names);
    }
}
