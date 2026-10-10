<?php

namespace BiztechEG\EasyPdfWord\View;

use BiztechEG\EasyPdfWord\Contracts\ViewRenderer;
use InvalidArgumentException;
use Throwable;

/**
 * Views as plain PHP files: the data becomes variables ($doc, $invoice, ...)
 * and whatever the file prints is the HTML. Escape values with
 * Html::escape() or the e() of your framework.
 */
class PhpViewRenderer implements ViewRenderer
{
    /** @param  list<string>  $paths  folders that view() looks in */
    public function __construct(private array $paths = []) {}

    public function file(string $path, array $data = []): string
    {
        if (str_ends_with(strtolower($path), '.blade.php')) {
            throw new InvalidArgumentException('['.basename($path).'] is a Blade view, which needs Laravel. Outside Laravel use a plain PHP view, such as pdf.html.php.');
        }

        if (! is_file($path)) {
            throw new InvalidArgumentException("View [{$path}] not found.");
        }

        return self::evaluate($path, $data);
    }

    /** "invoices.show" is invoices/show.html.php (or .php) in the first folder that has it. */
    public function view(string $name, array $data = []): string
    {
        $relative = str_replace('.', '/', $name);

        if (! preg_match('/^[A-Za-z0-9_\/-]+$/', $relative) || str_contains('/'.$relative.'/', '/../')) {
            throw new InvalidArgumentException('Invalid view name ['.mb_substr($name, 0, 60).'].');
        }

        foreach ($this->paths as $path) {
            foreach (['.html.php', '.php'] as $extension) {
                if (is_file($file = rtrim($path, '/\\').'/'.$relative.$extension)) {
                    return self::evaluate($file, $data);
                }
            }
        }

        throw new InvalidArgumentException("View [{$name}] not found in: ".($this->paths === [] ? 'no folders were given' : implode(', ', $this->paths)).'.');
    }

    /** Include the file with only the data in scope, as Laravel's PHP views do. */
    private static function evaluate(string $__path, array $__data): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            (static function () use ($__path, $__data) {
                extract($__data, EXTR_SKIP);

                require $__path;
            })();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }

        return ltrim((string) ob_get_clean());
    }
}
