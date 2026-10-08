<?php

namespace BiztechEG\EasyPdfWord\Templates;

use BiztechEG\EasyPdfWord\Support\Locale;
use RuntimeException;

/**
 * A template folder:
 *
 *   template.php      name, description, fields (validation rules), defaults
 *   pdf.blade.php     the PDF layout
 *   layout.php        optional code layout for both formats: returns fn (DocumentBuilder $doc, array $data, DocContext $context)
 *   word.php          optional Word layout, same shape; wins over layout.php for Word
 *   word.docx         optional Word file with ${placeholders}, designed in Word (wins over word.php)
 *   footer.blade.php  optional page footer, may use {page} and {pages}
 *   lang/{locale}.php optional labels, read in views with $doc->t('key')
 */
class Template
{
    private array $manifest;

    private array $translations = [];

    public function __construct(
        public readonly string $name,
        public readonly string $path,
    ) {
        $manifest = is_file($path.'/template.php') ? require $path.'/template.php' : [];
        $this->manifest = is_array($manifest) ? $manifest : [];
    }

    public function title(): string
    {
        return $this->manifest['title'] ?? $this->name;
    }

    public function description(): string
    {
        return $this->manifest['description'] ?? '';
    }

    /** @return string[] */
    public function locales(): array
    {
        return $this->manifest['locales'] ?? ['ar', 'en'];
    }

    /** Laravel validation rules for the template's data. */
    public function rules(): array
    {
        return $this->manifest['fields'] ?? [];
    }

    /** Default data merged under what the developer passes. */
    public function defaults(): array
    {
        return $this->manifest['defaults'] ?? [];
    }

    /**
     * Run the template's "prepare" callback, if any, to add computed values
     * (totals, counts, ...) to the data before it reaches the view. The
     * callback also gets the document theme: fn (array $data, array $theme).
     */
    public function prepare(array $data, array $theme = []): array
    {
        $prepare = $this->manifest['prepare'] ?? null;

        return is_callable($prepare) ? $prepare($data, $theme) : $data;
    }

    /** Example data used by previews and tests. */
    public function sample(): array
    {
        $sample = $this->manifest['sample'] ?? [];

        return is_callable($sample) ? $sample() : $sample;
    }

    public function theme(): array
    {
        return $this->manifest['theme'] ?? [];
    }

    /** @return string|array{0: float, 1: float}|null a name like "A4" or [width, height] in mm */
    public function paper(): string|array|null
    {
        return $this->manifest['paper'] ?? null;
    }

    public function orientation(): ?string
    {
        return $this->manifest['orientation'] ?? null;
    }

    public function margins(): ?array
    {
        return $this->manifest['margins'] ?? null;
    }

    public function pdfView(): string
    {
        $file = $this->path.'/pdf.blade.php';

        if (! is_file($file)) {
            throw new RuntimeException("Template [{$this->name}] has no pdf.blade.php.");
        }

        return $file;
    }

    public function hasPdfView(): bool
    {
        return is_file($this->path.'/pdf.blade.php');
    }

    /**
     * The code layout for Word: word.php, or layout.php when one layout
     * serves both formats.
     */
    public function wordLayout(): ?callable
    {
        return $this->layout('word.php') ?? $this->layout('layout.php');
    }

    /** The code layout for PDFs when there is no pdf.blade.php: layout.php, then word.php. */
    public function pdfLayout(): ?callable
    {
        return $this->layout('layout.php') ?? $this->layout('word.php');
    }

    public function wordFile(): ?string
    {
        return is_file($this->path.'/word.docx') ? $this->path.'/word.docx' : null;
    }

    public function supportsWord(): bool
    {
        return $this->wordFile() !== null || $this->hasLayout();
    }

    public function supportsPdf(): bool
    {
        return $this->hasPdfView() || $this->hasLayout();
    }

    private function hasLayout(): bool
    {
        return is_file($this->path.'/layout.php') || is_file($this->path.'/word.php');
    }

    private function layout(string $file): ?callable
    {
        if (! is_file($this->path.'/'.$file)) {
            return null;
        }

        $layout = require $this->path.'/'.$file;

        return is_callable($layout) ? $layout : null;
    }

    public function footerView(): ?string
    {
        return is_file($this->path.'/footer.blade.php') ? $this->path.'/footer.blade.php' : null;
    }

    public function headerView(): ?string
    {
        return is_file($this->path.'/header.blade.php') ? $this->path.'/header.blade.php' : null;
    }

    public function translations(string $locale): array
    {
        $language = Locale::language($locale);

        if ($language === null) {
            return [];
        }

        if (! array_key_exists($language, $this->translations)) {
            $file = $this->path."/lang/{$language}.php";
            $this->translations[$language] = is_file($file) ? (array) require $file : [];
        }

        return $this->translations[$language];
    }
}
