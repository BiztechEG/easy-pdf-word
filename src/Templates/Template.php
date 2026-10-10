<?php

namespace BiztechEG\EasyPdfWord\Templates;

use BiztechEG\EasyPdfWord\Support\Locale;
use RuntimeException;

/**
 * A template folder:
 *
 *   template.php      name, description, fields (validation rules), defaults
 *   pdf.html.php      the PDF layout as a plain PHP view (or pdf.blade.php, Laravel only)
 *   layout.php        optional code layout for both formats: returns fn (DocumentBuilder $doc, array $data, DocContext $context)
 *   word.php          optional Word layout, same shape; wins over layout.php for Word
 *   word.docx         optional Word file with ${placeholders}, designed in Word (wins over word.php)
 *   footer.html.php   optional page footer, may use {page} and {pages} (or footer.blade.php)
 *   header.html.php   optional page header, the same way (or header.blade.php)
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

    /** The PDF view: pdf.html.php, or pdf.blade.php in Laravel apps. */
    public function pdfView(): string
    {
        return $this->view('pdf') ?? throw new RuntimeException("Template [{$this->name}] has no pdf.html.php or pdf.blade.php.");
    }

    public function hasPdfView(): bool
    {
        return $this->view('pdf') !== null;
    }

    /**
     * The code layout for Word: word.php, or layout.php when one layout
     * serves both formats.
     */
    public function wordLayout(): ?callable
    {
        return $this->layout('word.php') ?? $this->layout('layout.php');
    }

    /** The code layout for PDFs when there is no PDF view: layout.php, then word.php. */
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
        return $this->view('footer');
    }

    public function headerView(): ?string
    {
        return $this->view('header');
    }

    /** "pdf" as pdf.html.php, which works everywhere, or else pdf.blade.php. */
    private function view(string $name): ?string
    {
        foreach (["{$name}.html.php", "{$name}.blade.php"] as $file) {
            if (is_file($this->path.'/'.$file)) {
                return $this->path.'/'.$file;
            }
        }

        return null;
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
