<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\DocumentFactory;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use BiztechEG\EasyPdfWord\Support\TempDir;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The temp_dir setting: Word files, .docx templates and ZIP files write
 * their temp files there instead of the system's temp folder.
 */
class TempDirTest extends TestCase
{
    private static string $root;

    public static function setUpBeforeClass(): void
    {
        self::$root = sys_get_temp_dir().'/easy-pdf-word-tests/temp-dir-'.bin2hex(random_bytes(4));
        @mkdir(self::$root.'/templates/greeting', 0777, true);
        file_put_contents(self::$root.'/templates/greeting/template.php', "<?php\n\nreturn ['title' => 'Greeting'];\n");

        $word = new PhpWord;
        $word->addSection()->addText('Hello ${name}');
        $word->save(self::$root.'/templates/greeting/word.docx');

        // A file where the folder should be: neither can be created.
        file_put_contents(self::$root.'/taken', '');
    }

    private static function docs(?string $tempDir): DocumentFactory
    {
        return EasyPdfWord::create([
            'temp_dir' => $tempDir,
            'templates' => ['paths' => [self::$root.'/templates']],
        ]);
    }

    /** @return list<string> the files left in the folder */
    private static function leftIn(string $dir): array
    {
        return array_values(array_diff((array) scandir($dir), ['.', '..']));
    }

    public function test_path_is_the_system_folder_unless_set(): void
    {
        $this->assertSame(sys_get_temp_dir(), TempDir::path(null));
        $this->assertSame(sys_get_temp_dir(), TempDir::path(''));

        $dir = self::$root.'/made/here';
        $this->assertSame($dir, TempDir::path($dir.'/'));
        $this->assertDirectoryExists($dir);
    }

    public function test_file_is_made_inside_the_folder(): void
    {
        $dir = self::$root.'/files';
        $file = TempDir::file($dir, 'easy-test');

        $this->assertSame(realpath($dir), realpath(dirname($file)));
        $this->assertStringStartsWith('easy-test', basename($file));
        unlink($file);
    }

    public function test_a_folder_that_cannot_be_made_is_reported(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Set a writable folder in the temp_dir setting.');

        TempDir::file(self::$root.'/taken', 'easy-test');
    }

    public function test_phpword_temp_folder_is_set_back(): void
    {
        $before = Settings::getTempDir();
        $dir = self::$root.'/phpword';

        $this->assertSame($dir, TempDir::forPhpWord($dir, fn () => Settings::getTempDir()));
        $this->assertSame($before, Settings::getTempDir());

        try {
            TempDir::forPhpWord($dir, fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
        }

        $this->assertSame($before, Settings::getTempDir());
        $this->assertSame('x', TempDir::forPhpWord(null, fn () => 'x'));
    }

    public function test_word_files_use_the_folder_and_leave_nothing_in_it(): void
    {
        $dir = self::$root.'/word';

        $content = self::docs($dir)->make()->locale('ar')->paragraph('مرحبا')->word()->content();

        $this->assertStringStartsWith('PK', $content);
        $this->assertSame([], self::leftIn($dir));

        $this->expectExceptionMessage('temp_dir');
        self::docs(self::$root.'/taken')->make()->paragraph('x')->word()->content();
    }

    public function test_docx_templates_use_the_folder(): void
    {
        $dir = self::$root.'/docx';
        $before = Settings::getTempDir();

        $content = self::docs($dir)->template('greeting', ['name' => 'Amr'])->word()->content();

        $this->assertStringStartsWith('PK', $content);
        $this->assertSame([], self::leftIn($dir));
        $this->assertSame($before, Settings::getTempDir());

        $this->expectExceptionMessage('temp_dir');
        self::docs(self::$root.'/taken')->template('greeting', ['name' => 'Amr'])->word()->content();
    }

    public function test_zip_files_use_the_folder(): void
    {
        $dir = self::$root.'/zip';
        $docs = self::docs($dir);

        $zip = $docs->zip(['a.docx' => $docs->make()->paragraph('a')->word()])->content();

        $this->assertStringStartsWith('PK', $zip);
        $this->assertSame([], self::leftIn($dir));

        $this->expectExceptionMessage('temp_dir');
        $taken = self::docs(self::$root.'/taken');
        $taken->zip(['a.docx' => self::docs(null)->make()->paragraph('a')->word()])->content();
    }
}
