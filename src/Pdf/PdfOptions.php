<?php

namespace BiztechEG\EasyPdfWord\Pdf;

/**
 * Page and document settings handed to a PDF engine.
 *
 * Header and footer HTML may use {page} and {pages}; each engine replaces
 * them with its own page-number syntax.
 */
class PdfOptions
{
    /**
     * @param  string|array{0: float, 1: float}  $paper  a name like "A4" or [width, height] in mm
     * @param  array{0: float, 1: float, 2: float, 3: float}  $margins  mm: top, right, bottom, left
     * @param  array{text: string, opacity: float, color: string}|null  $watermark  text across every page
     * @param  array{user: string, owner: ?string, allow: list<string>}|null  $protection  passwords and what readers may do
     */
    public function __construct(
        public string|array $paper = 'A4',
        public string $orientation = 'portrait',
        public array $margins = [15, 15, 15, 15],
        public string $direction = 'ltr',
        public string $locale = 'en',
        public string $font = 'cairo',
        public ?string $header = null,
        public ?string $footer = null,
        public ?string $title = null,
        public ?string $author = null,
        public string $numerals = 'latin',
        public ?array $watermark = null,
        public ?array $protection = null,
    ) {}

    public function isLandscape(): bool
    {
        return strtolower($this->orientation) === 'landscape' || strtoupper($this->orientation) === 'L';
    }

    /**
     * Paper size in mm as [width, height], orientation applied.
     *
     * @return array{0: float, 1: float}
     */
    public function paperSize(): array
    {
        $size = is_array($this->paper)
            ? array_values($this->paper)
            : (self::PAPER_SIZES[strtoupper($this->paper)] ?? throw self::unknownPaper($this->paper));

        return $this->isLandscape() ? [max($size), min($size)] : [min($size), max($size)];
    }

    public static function unknownPaper(string $paper): \InvalidArgumentException
    {
        return new \InvalidArgumentException("Unknown paper size [{$paper}]. Use one of ".implode(', ', array_keys(self::PAPER_SIZES)).', or [width, height] in mm.');
    }

    public const PAPER_SIZES = [
        'A2' => [420, 594],
        'A3' => [297, 420],
        'A4' => [210, 297],
        'A5' => [148, 210],
        'A6' => [105, 148],
        'B4' => [250, 353],
        'B5' => [176, 250],
        'LETTER' => [215.9, 279.4],
        'LEGAL' => [215.9, 355.6],
        'TABLOID' => [279.4, 431.8],
        'EXECUTIVE' => [184.15, 266.7],
    ];
}
