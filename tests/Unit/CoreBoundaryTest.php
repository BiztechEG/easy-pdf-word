<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The code that will become the framework-free PHP core must not use
 * Laravel: no Illuminate classes and no Laravel helper functions.
 */
class CoreBoundaryTest extends TestCase
{
    /** Folders and files of the future core, relative to src/. */
    private const CORE = [
        'Arabic',
        'Builder',
        'Contracts',
        'Exceptions',
        'Fonts',
        'Output',
        'Support',
        'Templates',
        'Validation',
        'View',
        'Word',
        'Zatca',
        'Document.php',
        'DocumentFactory.php',
        'DocumentServices.php',
        'EasyPdfWord.php',
        'Pdf/Drivers/ArabicLanguageToFont.php',
        'Pdf/Drivers/BrowsershotDriver.php',
        'Pdf/Drivers/GotenbergDriver.php',
        'Pdf/Drivers/MpdfDriver.php',
        'Pdf/PdfManager.php',
        'Pdf/PdfOptions.php',
        'Pdf/PdfProtector.php',
        'Pdf/Watermark.php',
        'helpers.php',
    ];

    /** Bridges that name Laravel only to fit in when it is installed. */
    private const ALLOWED = [
        'Support/HtmlString.php',
    ];

    private const LARAVEL_HELPERS = [
        'abort', 'app', 'app_path', 'base_path', 'blank', 'cache', 'class_basename', 'collect', 'config',
        'config_path', 'data_get', 'data_set', 'database_path', 'dispatch', 'e', 'event', 'filled', 'head',
        'info', 'last', 'lang_path', 'logger', 'now', 'optional', 'public_path', 'report', 'request',
        'rescue', 'resolve', 'resource_path', 'response', 'retry', 'session', 'storage_path', 'str', 'tap',
        'throw_if', 'throw_unless', 'today', 'trans', 'trans_choice', 'url', 'validator', 'value', 'view',
        'with', '__',
    ];

    public function test_core_code_does_not_use_laravel(): void
    {
        $problems = [];

        foreach ($this->coreFiles() as $relative => $path) {
            if (in_array($relative, self::ALLOWED, true)) {
                continue;
            }

            foreach ($this->laravelUses((string) file_get_contents($path)) as [$line, $what]) {
                $problems[] = "src/{$relative}:{$line} uses {$what}";
            }
        }

        $this->assertSame([], $problems, "Core code must work without Laravel:\n".implode("\n", $problems));
    }

    public function test_bundled_templates_do_not_use_laravel_or_carbon(): void
    {
        $base = dirname(__DIR__, 2).'/resources/templates';
        $problems = [];
        $blade = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $relative = substr((string) $file, strlen($base) + 1);

            if (str_ends_with($relative, '.blade.php')) {
                $blade[] = $relative;
            } elseif (str_ends_with($relative, '.php')) {
                foreach ($this->laravelUses((string) file_get_contents((string) $file), 'Illuminate|Carbon') as [$line, $what]) {
                    $problems[] = "resources/templates/{$relative}:{$line} uses {$what}";
                }
            }
        }

        sort($blade);

        $this->assertSame([], $problems, "Bundled templates must work without Laravel:\n".implode("\n", $problems));
        $this->assertSame([], $blade, 'Bundled templates are plain PHP, so they work without Laravel.');
    }

    public function test_the_check_finds_laravel_code(): void
    {
        $code = <<<'PHP'
            <?php
            use Illuminate\Support\Arr;
            // config('ignored in a comment')
            $a = collect([1]);
            $b = \Illuminate\Support\Str::upper('x');
            $c = $object->config('a method is fine');
            $d = Html::escape('a static call is fine');
            function e($value) {}
            $e = e('x');
            PHP;

        $this->assertSame(
            [[2, 'Illuminate\Support\Arr'], [4, 'collect()'], [5, '\Illuminate\Support\Str'], [9, 'e()']],
            $this->laravelUses($code),
        );
    }

    /** @return array<string, string> path relative to src/ => absolute path */
    private function coreFiles(): array
    {
        $src = dirname(__DIR__, 2).'/src';
        $files = [];

        foreach (self::CORE as $entry) {
            $path = $src.'/'.$entry;
            $this->assertFileExists($path, "src/{$entry} is listed as core but does not exist.");

            $paths = is_dir($path)
                ? array_map('strval', iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS))))
                : [$path];

            foreach ($paths as $file) {
                if (str_ends_with($file, '.php')) {
                    $files[substr($file, strlen($src) + 1)] = $file;
                }
            }
        }

        ksort($files);

        return $files;
    }

    /** @return list<array{int, string}> line => what was used */
    private function laravelUses(string $code, string $namespaces = 'Illuminate'): array
    {
        $tokens = array_values(array_filter(
            token_get_all($code),
            fn ($token) => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));
        $found = [];

        foreach ($tokens as $i => $token) {
            if (! is_array($token)) {
                continue;
            }

            [$id, $text, $line] = $token;

            if (in_array($id, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && preg_match('/^\\\\?('.$namespaces.')\\\\/', $text)) {
                $found[] = [$line, $text];

                continue;
            }

            $previous = $tokens[$i - 1] ?? null;
            $next = $tokens[$i + 1] ?? null;
            $isCall = $id === T_STRING && $next === '('
                && ! (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true));

            if ($isCall && in_array(strtolower($text), self::LARAVEL_HELPERS, true)) {
                $found[] = [$line, $text.'()'];
            }
        }

        return $found;
    }
}
