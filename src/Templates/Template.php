<?php

namespace BiztechEG\EasyPdfWord\Templates;

use RuntimeException;

/**
 * A template folder:
 *
 *   template.php      name, description, fields (validation rules), defaults
 *   pdf.blade.php     the PDF layout
 *   word.php          optional Word layout: returns fn (DocumentBuilder $word, array $data, DocContext $doc)
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
     * (totals, counts, ...) to the data before it reaches the view.
     */
    public function prepare(array $data): array
    {
        $prepare = $this->manifest['prepare'] ?? null;

        return is_callable($prepare) ? $prepare($data) : $data;
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

    public function paper(): ?string
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

    /** The word.php layout callback, if the template has one. */
    public function wordLayout(): ?callable
    {
        $file = $this->path.'/word.php';

        if (! is_file($file)) {
            return null;
        }

        $layout = require $file;

        return is_callable($layout) ? $layout : null;
    }

    public function wordFile(): ?string
    {
        return is_file($this->path.'/word.docx') ? $this->path.'/word.docx' : null;
    }

    public function supportsWord(): bool
    {
        return $this->wordFile() !== null || is_file($this->path.'/word.php');
    }

    public function supportsPdf(): bool
    {
        return $this->hasPdfView() || is_file($this->path.'/word.php');
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
        $language = strtolower(preg_split('/[-_]/', $locale)[0]);

        if (! array_key_exists($language, $this->translations)) {
            $file = $this->path."/lang/{$language}.php";
            $this->translations[$language] = is_file($file) ? (array) require $file : [];
        }

        return $this->translations[$language];
    }
}
