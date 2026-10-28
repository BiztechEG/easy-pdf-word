<?php

namespace BiztechEG\EasyPdfWord\Pdf;

/**
 * Page and document settings handed to a PDF engine.
 *
 * Header and footer HTML may use {page} and {pages}; each engine replaces
 * them with its own page-number syntax. firstHeader and firstFooter replace
 * them on the first page: null keeps the normal one, false leaves it empty.
 */
class PdfOptions
{
    /**
     * @param  string|array{0: float, 1: float}  $paper  a name like "A4" or [width, height] in mm
     * @param  array{0: float, 1: float, 2: float, 3: float}  $margins  mm: top, right, bottom, left
     * @param  array{text: string, opacity: float, color: string}|null  $watermark  text across every page
     * @param  array{user: string, owner: ?string, allow: list<string>}|null  $protection  passwords and what readers may do
     * @param  float|null  $headerDistance  mm from the top of the page to the header; null for the engine's default
     * @param  float|null  $footerDistance  mm from the bottom of the page to the footer
     * @param  bool  $fitHeaders  the margins are a minimum: a taller header or footer pushes the body (mPDF)
     * @param  array{src: string, opacity: float, width: float}|null  $watermarkImage  an image (data URI) across every page, width in mm
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
        public string|false|null $firstHeader = null,
        public string|false|null $firstFooter = null,
        public ?float $headerDistance = null,
        public ?float $footerDistance = null,
        public bool $fitHeaders = false,
        public ?array $watermarkImage = null,
    ) {}

    /** Whether the first page has its own header or footer. */
    public function hasFirstPage(): bool
    {
        return $this->firstHeader !== null || $this->firstFooter !== null;
    }

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

    /** The width of the page's text in mm: the paper less the side margins. */
    public function contentWidth(): float
    {
        return max(1.0, $this->paperSize()[0] - (float) $this->margins[1] - (float) $this->margins[3]);
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
