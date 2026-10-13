<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Arabic\Direction;
use BiztechEG\EasyPdfWord\Support\Language;
use PHPUnit\Framework\TestCase;

class LanguageTest extends TestCase
{
    protected function tearDown(): void
    {
        Language::flush();

        parent::tearDown();
    }

    public function test_reads_the_bundled_files(): void
    {
        $this->assertSame('العربية', Language::get('ar', 'name'));
        $this->assertSame('سبتمبر', Language::get('ar_EG', 'months')[8]);
        $this->assertSame('Thursday', Language::get('en', 'days.4'));
        $this->assertSame('ر.س', Language::get('ar', 'currencies.SAR'));
        $this->assertTrue(Language::has('ar-SA'));
        $this->assertFalse(Language::has('xx'));
        $this->assertFalse(Language::has('../en'));
    }

    public function test_a_language_without_a_file_or_key_uses_english(): void
    {
        $this->assertSame('January', Language::get('zz', 'months.0'));
        $this->assertSame(':amount only', Language::get('ar', 'amount_in_words.only'));
        $this->assertSame('fallback', Language::get('ar', 'missing', 'fallback'));
    }

    public function test_added_folders_add_languages_and_replace_keys(): void
    {
        Language::addPath(__DIR__.'/../fixtures/lang');

        $this->assertTrue(Language::has('xx'));
        $this->assertSame('M9', Language::get('xx', 'months.8'));
        $this->assertSame('Sunday', Language::get('xx', 'days.0'));
        $this->assertSame(' / ', Language::get('en', 'list_separator'));
        $this->assertSame(' / ', Language::get('xx', 'list_separator'));
        $this->assertSame('January', Language::get('en', 'months.0'));
        $this->assertContains('xx', Language::available());
    }

    public function test_every_bundled_file_is_complete(): void
    {
        $keys = array_keys(Language::data('en'));

        foreach (Language::available() as $language) {
            $file = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/lang/'.$language.'.json'), true);

            $this->assertIsArray($file, $language);
            $this->assertSame(Direction::forLocale($language), $file['direction'], $language);
            $this->assertCount(12, $file['months'], $language);
            $this->assertCount(7, $file['days'], $language);
            $this->assertSame([], array_diff(array_keys($file), $keys), $language.' has keys English does not');
        }
    }
}
