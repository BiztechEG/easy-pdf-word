<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Templates\Template;
use PHPUnit\Framework\TestCase;

class TemplateTranslationsTest extends TestCase
{
    private function note(): Template
    {
        return new Template('note', dirname(__DIR__).'/fixtures/plain-templates/note');
    }

    public function test_labels_can_be_json_files(): void
    {
        $this->assertSame(['to' => 'Sayın :name', 'page' => 'Sayfa'], $this->note()->translations('tr_TR'));
    }

    public function test_php_labels_win_over_json_ones(): void
    {
        $this->assertSame(['to' => 'To :name', 'signed' => 'Signed', 'page' => 'Page'], $this->note()->translations('en'));
        $this->assertSame([], $this->note()->translations('fr'));
        $this->assertSame([], $this->note()->translations('../en'));
    }
}
