<?php

namespace BiztechEG\EasyPdfWord\Support;

use Closure;
use PhpOffice\PhpWord\Settings;
use ReflectionProperty;
use RuntimeException;

/**
 * Where Word files, .docx templates, ZIP files and Chromium write their temp
 * files: the "temp_dir" setting, or the system's temp folder when it is not
 * set. On shared hosting the system folder is shared with other accounts, so
 * a folder of the app's own keeps rendered documents out of it.
 *
 * @internal
 */
final class TempDir
{
    /**
     * The folder, created when missing. A configured folder is created 0775
     * like mPDF's, because the web server and a queue worker may be
     * different users; the files in it are only readable by their owner.
     */
    public static function path(?string $configured): string
    {
        if ($configured === null || $configured === '') {
            return sys_get_temp_dir();
        }

        $dir = rtrim($configured, '/\\');

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create the temp folder [{$dir}]. Set a writable folder in the temp_dir setting.");
        }

        if (! is_writable($dir)) {
            throw new RuntimeException("The temp folder [{$dir}] is not writable. Set a writable folder in the temp_dir setting.");
        }

        return $dir;
    }

    /** A new empty file in the folder, which the caller removes. */
    public static function file(?string $configured, string $prefix): string
    {
        $dir = self::path($configured);
        $file = @tempnam($dir, $prefix);

        // tempnam() quietly falls back to the system folder when it cannot write here.
        if ($file === false || realpath(dirname($file)) !== realpath($dir)) {
            if ($file !== false) {
                @unlink($file);
            }

            throw new RuntimeException("Cannot create a temp file in [{$dir}]. Set a writable folder in the temp_dir setting.");
        }

        return $file;
    }

    /**
     * Run $callback with PhpWord's own temp files (TemplateProcessor, images
     * of rich text) in the configured folder. PhpWord's setting is global, so
     * it is set back afterwards.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function forPhpWord(?string $configured, Closure $callback): mixed
    {
        if ($configured === null || $configured === '') {
            return $callback();
        }

        $setting = new ReflectionProperty(Settings::class, 'tempDir');
        $previous = (string) $setting->getValue();
        Settings::setTempDir(self::path($configured));

        try {
            return $callback();
        } finally {
            Settings::setTempDir($previous);
        }
    }
}
