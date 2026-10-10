<?php

namespace BiztechEG\EasyPdfWord;

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Output\File;
use BiztechEG\EasyPdfWord\Pdf\PdfManager;
use BiztechEG\EasyPdfWord\Templates\TemplateRegistry;
use Closure;

/**
 * Starts documents. In plain PHP, EasyPdfWord::create() returns one; in
 * Laravel the Doc facade is its subclass DocFactory.
 */
class DocumentFactory
{
    public function __construct(
        protected DocumentServices $services,
        protected TemplateRegistry $templates,
    ) {}

    /** Start from a ready-made or project template. */
    public function template(string $name, array $data = []): Document
    {
        return $this->documentClass()::forTemplate($this->templates->get($name), $this->services)->data($data);
    }

    /** Start from a view: a Blade view in Laravel, or a PHP file under the "views.paths" folders. */
    public function view(string $view, array $data = []): Document
    {
        return $this->documentClass()::forView($view, $this->services)->data($data);
    }

    /** Start from an HTML string or body fragment. */
    public function html(string $html): Document
    {
        return $this->documentClass()::forHtml($html, $this->services);
    }

    /**
     * Build a document in code, block by block; it renders to both PDF and
     * Word: ->make()->heading('...')->table($rows)->word().
     */
    public function make(): Document
    {
        return $this->documentClass()::forBuilder(new DocumentBuilder, $this->services);
    }

    /**
     * Several files in one .zip: zip([$invoice->pdf(), $invoice->word()])
     * or zip(['فاتورة.pdf' => $pdf]).
     *
     * @param  array<int|string, File>  $files
     */
    public function zip(array $files, string $filename = 'documents.zip'): File
    {
        return File::zipOf($files, $filename);
    }

    public function templates(): TemplateRegistry
    {
        return $this->templates;
    }

    public function fonts(): FontRegistry
    {
        return $this->services->fonts;
    }

    public function pdfManager(): PdfManager
    {
        return $this->services->pdf;
    }

    public function services(): DocumentServices
    {
        return $this->services;
    }

    /** Register a custom PDF engine: ->extend('my-engine', fn () => new MyDriver). */
    public function extend(string $driver, Closure $callback): static
    {
        $this->services->pdf->extend($driver, $callback);

        return $this;
    }

    /** @return class-string<Document> */
    protected function documentClass(): string
    {
        return Document::class;
    }
}
