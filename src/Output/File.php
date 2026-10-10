<?php

namespace BiztechEG\EasyPdfWord\Output;

use Closure;
use RuntimeException;
use ZipArchive;

/**
 * A rendered (or about to be rendered) file. Rendering happens once, on the
 * first call that needs the bytes. In Laravel the files are PdfDocument,
 * WordDocument and ZipFile, which add disks, downloads and mail.
 */
class File
{
    private ?string $content = null;

    private ?string $engine = null;

    /**
     * @param  Closure(): array{0: string, 1: string}  $renderer  returns [bytes, engine name]
     */
    public function __construct(
        private Closure $renderer,
        protected string $filename = 'document',
        private string $mimeType = 'application/octet-stream',
        private string $extension = '',
    ) {}

    public static function pdf(Closure $renderer, string $filename = 'document.pdf'): self
    {
        return new self($renderer, $filename, 'application/pdf', 'pdf');
    }

    public static function word(Closure $renderer, string $filename = 'document.docx'): self
    {
        return new self($renderer, $filename, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx');
    }

    public static function zip(Closure $renderer, string $filename = 'documents.zip'): self
    {
        return new self($renderer, $filename, 'application/zip', 'zip');
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function extension(): string
    {
        return $this->extension;
    }

    public function content(): string
    {
        if ($this->content === null) {
            [$this->content, $this->engine] = ($this->renderer)();
        }

        return $this->content;
    }

    public function toString(): string
    {
        return $this->content();
    }

    public function base64(): string
    {
        return base64_encode($this->content());
    }

    /** The engine that produced the file, after a fallback too. */
    public function engine(): string
    {
        $this->content();

        return $this->engine;
    }

    /** The file name used for downloads and attachments, with its extension. */
    public function filename(): string
    {
        return $this->cleanFilename($this->filename);
    }

    /** Write the file to a local path, creating its folder. */
    public function save(string $path): string
    {
        // Another worker may create the folder at the same moment.
        if (! is_dir(dirname($path)) && ! @mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Could not create the folder ['.dirname($path).'].');
        }

        if (@file_put_contents($path, $this->content()) === false) {
            throw new RuntimeException("Could not write [{$path}].");
        }

        return $path;
    }

    /**
     * Send the file to the browser from plain PHP: as a download, or shown
     * in the browser with $inline. Nothing may have been output before.
     */
    public function send(?string $filename = null, bool $inline = false): void
    {
        if (headers_sent($file, $line)) {
            throw new RuntimeException("Cannot send the file: output already started at {$file}:{$line}.");
        }

        $content = $this->content();
        header('Content-Type: '.$this->mimeType());
        header('Content-Length: '.strlen($content));
        header('Content-Disposition: '.$this->disposition($inline ? 'inline' : 'attachment', $filename));
        echo $content;
    }

    /** A Content-Disposition value: the UTF-8 name, and a Latin fallback for old clients. */
    protected function disposition(string $type, ?string $filename = null): string
    {
        $filename = $this->cleanFilename($filename ?? $this->filename);
        $fallback = $this->fallbackFilename($filename);
        $header = $type.'; filename="'.str_replace(['\\', '"'], ['\\\\', '\\"'], $fallback).'"';

        return $fallback === $filename ? $header : $header."; filename*=utf-8''".rawurlencode($filename);
    }

    /** The name in Latin letters ("%" is not allowed in this fallback). */
    protected function fallbackFilename(string $filename): string
    {
        $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename) : $filename;
        $fallback = preg_replace('/[^\x20-\x24\x26-\x7E]/', '_', $ascii) ?? '';

        if (trim(pathinfo($fallback, PATHINFO_FILENAME), ' _-.') === '') {
            $fallback = 'document'.(pathinfo($filename, PATHINFO_EXTENSION) !== '' ? '.'.pathinfo($filename, PATHINFO_EXTENSION) : '');
        }

        return $fallback;
    }

    protected function cleanFilename(string $filename): string
    {
        // Slashes are not allowed in a file name.
        $filename = str_replace(['/', '\\'], '-', $filename);
        $extension = '.'.$this->extension();

        return $this->extension() === '' || str_ends_with(strtolower($filename), $extension) ? $filename : $filename.$extension;
    }

    /**
     * Several files in one .zip: names as keys, or a list where each file keeps its own name.
     *
     * @param  array<int|string, File>  $files
     */
    public static function archive(array $files): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP files need the PHP zip extension (ext-zip).');
        }

        $path = tempnam(sys_get_temp_dir(), 'easy-pdf-word-zip');

        if ($path === false) {
            throw new RuntimeException('Could not create a ZIP file in '.sys_get_temp_dir().'.');
        }

        $zip = new ZipArchive;

        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create a ZIP file in '.sys_get_temp_dir().'.');
            }

            $names = [];

            foreach ($files as $key => $file) {
                // Names never hold folders, so the archive cannot unpack outside its folder.
                $name = ltrim($file->cleanFilename(is_string($key) ? $key : $file->filename()), '. ');

                if ($name === '' || $name === $file->extension()) {
                    $name = 'document.'.$file->extension();
                }

                $zip->addFromString(self::unique($name, $names), $file->content());
            }

            // A failed write (a full disk) must not hand out an empty archive.
            if (! $zip->close()) {
                throw new RuntimeException('Could not write the ZIP file: '.$zip->getStatusString());
            }

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /** "invoice.pdf", then "invoice (2).pdf", "invoice (3).pdf" ... */
    private static function unique(string $name, array &$names): string
    {
        $unique = $name;
        $info = pathinfo($name);
        $extension = isset($info['extension']) ? '.'.$info['extension'] : '';

        for ($i = 2; isset($names[mb_strtolower($unique)]); $i++) {
            $unique = $info['filename']." ({$i}){$extension}";
        }

        $names[mb_strtolower($unique)] = true;

        return $unique;
    }
}
