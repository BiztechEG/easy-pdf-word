<?php

namespace BiztechEG\EasyPdfWord\Word;

use BiztechEG\EasyPdfWord\Arabic\Numerals;
use BiztechEG\EasyPdfWord\Builder\BlockText;
use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Exceptions\DriverNotAvailable;
use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Pdf\Watermark;
use BiztechEG\EasyPdfWord\Support\Color;
use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Support\HtmlSanitizer;
use BiztechEG\EasyPdfWord\Support\Qr;
use BiztechEG\EasyPdfWord\Support\Stamp;
use BiztechEG\EasyPdfWord\Support\TempDir;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Table as TableStyle;
use ReflectionProperty;
use Throwable;

/**
 * Turns builder blocks into a .docx with PhpWord.
 *
 * Word shapes Arabic itself; what matters is that every paragraph is marked
 * bidi, Arabic runs are marked rtl (so Word uses the complex-script font and
 * size), and tables are laid out right to left (bidiVisual).
 */
class WordRenderer
{
    private const TWIPS_PER_MM = 56.6929;

    private const POINTS_PER_MM = 2.8346;

    /** heading() takes levels 1 to 3; rich text has h4 too. */
    private const HEADING_SIZES = [1 => 18, 2 => 14, 3 => 12, 4 => 11];

    private DocContext $doc;

    private PhpWord $word;

    private bool $rtl;

    private string $numerals;

    /** The lists in the file so far, and the numbering style of the one being written. */
    private int $lists = 0;

    private string $listStyle = '';

    /** Whether the Heading1 to Heading4 styles rich text uses are registered. */
    private bool $headingStyles = false;

    /** While a header or footer is written: {page} and {pages} become Word fields. */
    private bool $pageFields = false;

    /** @param  FontRegistry|null  $fonts  maps a run's registered "font" to its Word name */
    /** @param  string|null  $tempDir  the temp_dir setting; null for the system's temp folder */
    public function __construct(private array $config = [], private ?FontRegistry $fonts = null, private ?string $tempDir = null) {}

    public static function isAvailable(): bool
    {
        return class_exists(PhpWord::class);
    }

    /**
     * $pages holds the headers and footers ("header", "footer",
     * "firstHeader", "firstFooter"), each blocks, HTML (written as one line
     * of text) or, on the first page, false for none. Without it the HTML
     * in $options is used.
     *
     * @param  array<string, DocumentBuilder|string|false|null>|null  $pages
     */
    public function render(DocumentBuilder $builder, DocContext $doc, PdfOptions $options, ?array $pages = null): string
    {
        if (! self::isAvailable()) {
            throw DriverNotAvailable::missingPackage('word', 'phpoffice/phpword');
        }

        return TempDir::forPhpWord($this->tempDir, fn () => $this->write($builder, $doc, $options, $pages));
    }

    private function write(DocumentBuilder $builder, DocContext $doc, PdfOptions $options, ?array $pages): string
    {
        $this->doc = $doc;
        $this->rtl = $doc->isRtl();
        $this->numerals = $doc->numerals;

        $previousRtl = Settings::isDefaultRtl();
        $previousEscaping = Settings::isOutputEscapingEnabled();
        Settings::setDefaultRtl($this->rtl);
        Settings::setOutputEscapingEnabled(true);
        $file = null;

        try {
            $word = $this->word = new PhpWord;
            $word->setDefaultFontName($this->config['font'] ?? 'Arial');
            $word->setDefaultFontSize((float) ($this->config['font_size'] ?? 11));
            $word->getSettings()->setThemeFontLang(new Language(Language::EN_US, null, $this->arabicLocale()));

            if ($options->title) {
                $word->getDocInfo()->setTitle($options->title);
            }

            if ($options->author) {
                $word->getDocInfo()->setCreator($options->author);
            }

            $section = $word->addSection($this->sectionStyle($options));
            $contentWidth = $this->contentWidth($options);
            $this->pages($section, $pages ?? [
                'header' => $options->header,
                'footer' => $options->footer,
                'firstHeader' => $options->firstHeader,
                'firstFooter' => $options->firstFooter,
            ], $contentWidth, $this->watermark($options));

            foreach ($builder->blocks() as $block) {
                $this->block($section, $block, $contentWidth);
            }

            $file = TempDir::file($this->tempDir, 'easy-docx');
            IOFactory::createWriter($word, 'Word2007')->save($file);

            return (string) file_get_contents($file);
        } finally {
            Settings::setDefaultRtl($previousRtl);
            Settings::setOutputEscapingEnabled($previousEscaping);

            if ($file !== null) {
                @unlink($file);
            }
        }
    }

    /**
     * The section's headers and footers. Word takes both the header and the
     * footer of the first page from its FIRST variants once either exists,
     * so the other one is repeated there.
     */
    private function pages(Section $section, array $pages, float $width, ?array $watermark = null): void
    {
        $header = $pages['header'] ?? null;
        $footer = $pages['footer'] ?? null;

        // A watermark lives in a header, so it gets one even when empty.
        if (self::hasContent($header) || $watermark !== null) {
            $this->pagePart($container = $section->addHeader(), $header, $width);
            $this->addWatermark($container, $watermark);
        }

        if (self::hasContent($footer)) {
            $this->pagePart($section->addFooter(), $footer, $width);
        }

        $firstHeader = $pages['firstHeader'] ?? null;
        $firstFooter = $pages['firstFooter'] ?? null;

        // An empty FIRST part is how the first page gets none.
        if ($firstHeader !== null || $firstFooter !== null) {
            $this->pagePart($container = $section->addHeader(Header::FIRST), $firstHeader ?? $header, $width);
            $this->addWatermark($container, $watermark);
            $this->pagePart($section->addFooter(Footer::FIRST), $firstFooter ?? $footer, $width);
        }
    }

    private static function hasContent(DocumentBuilder|string|false|null $part): bool
    {
        return $part instanceof DocumentBuilder ? ! $part->isEmpty() : is_string($part) && $part !== '';
    }

    /**
     * The image watermark for Word: in the middle of the page, behind the
     * text. Word's images have no opacity, so the image is faded with GD.
     *
     * @return array{bytes: string, style: array}|null
     */
    private function watermark(PdfOptions $options): ?array
    {
        $mark = $options->watermarkImage;
        $bytes = $mark === null ? null : WordImage::load($mark['src']);
        $image = $bytes === null ? false : @imagecreatefromstring($bytes);

        if ($image === false) {
            if ($mark !== null) {
                $this->doc->warn('easy-pdf-word: the watermark image must be a PNG, JPEG, GIF, WebP or BMP image for Word files; the Word file is made without it.');
            }

            return null;
        }

        [$width, $height] = Watermark::imageSize($mark);

        return ['bytes' => self::faded($image, $mark['opacity'], $width), 'style' => [
            'width' => round($width * self::POINTS_PER_MM, 2),
            'height' => round($height * self::POINTS_PER_MM, 2),
            'wrappingStyle' => 'behind',
            'posHorizontal' => 'center',
            'posHorizontalRel' => 'page',
            'posVertical' => 'center',
            'posVerticalRel' => 'page',
        ]];
    }

    private function addWatermark(Header $header, ?array $watermark): void
    {
        if ($watermark !== null) {
            $header->addWatermark($watermark['bytes'], $watermark['style']);
        }
    }

    /** The image at 150 dpi for its width in mm, with every pixel's alpha scaled by $opacity, as PNG. */
    private static function faded(\GdImage $image, float $opacity, float $widthMm): string
    {
        $width = max(1, min(imagesx($image), (int) round($widthMm / 25.4 * 150)));
        $height = max(1, (int) round(imagesy($image) * $width / imagesx($image)));
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($canvas, $x, $y);
                $alpha = 127 - (int) round((127 - (($rgba >> 24) & 0x7F)) * $opacity);
                imagesetpixel($canvas, $x, $y, ($alpha << 24) | ($rgba & 0xFFFFFF));
            }
        }

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    private function pagePart(AbstractContainer $container, DocumentBuilder|string|false|null $part, float $width): void
    {
        if (is_string($part) && $part !== '') {
            $this->pageText($container, $part);
        } elseif ($part instanceof DocumentBuilder) {
            $this->pageFields = true;

            try {
                foreach ($part->blocks() as $block) {
                    $this->block($container, $block, $width);
                }
            } finally {
                $this->pageFields = false;
            }
        }
    }

    private function block(AbstractContainer $container, array $block, float $width): void
    {
        match ($block['type']) {
            'heading' => $this->heading($container, $block),
            'paragraph' => $this->paragraph($container, $block['runs'], $block['style']),
            'list' => $this->listBlock($container, $block['items'], $block['options']),
            'richText' => $this->richText($container, $block, $width),
            'table' => $this->table($container, $block, $width),
            'image' => $this->image($container, $this->doc->image($block['source']), $block['width'], $block['align']),
            'qr' => $this->image($container, Qr::dataUri($block['value']), $block['width'], $block['align']),
            'spacer' => $container->addText('', ['size' => 1], ['spaceBefore' => 0, 'spaceAfter' => (int) ($block['height'] * self::TWIPS_PER_MM)]),
            'pageBreak' => $container->addPageBreak(),
            'line' => $container->addText('', [], $this->paragraphStyle([]) + ['borderBottomSize' => 6, 'borderBottomColor' => $this->color($block['color'] ?? $this->doc->theme('border', '#E5E7EB'))]),
            default => null,
        };
    }

    private function heading(AbstractContainer $container, array $block): void
    {
        $style = $block['style'] + [
            'size' => self::HEADING_SIZES[$block['level']],
            'bold' => true,
            'color' => $block['level'] === 1 ? $this->doc->theme('primary') : null,
        ];

        // A heading stays on the same page as the text after it.
        $this->paragraph($container, [['text' => $block['text']]], $style + ['space_after' => 4, 'keep_next' => true]);
    }

    private function paragraph(AbstractContainer $container, array $runs, array $style): void
    {
        $this->writeRuns($container->addTextRun($this->paragraphStyle($style)), $runs, $style);
    }

    /**
     * A list: one numbering style per list, so each starts at its own
     * number, and a list paragraph per item, right to left in RTL documents.
     */
    private function listBlock(AbstractContainer $container, array $items, array $options, int $depth = 0): void
    {
        if ($depth === 0) {
            $this->listStyle = 'EasyList'.(++$this->lists);
            $this->word->addNumberingStyle($this->listStyle, WordList::style(
                ! empty($options['ordered']),
                $this->numerals === Numerals::ARABIC,
                (int) ($options['start'] ?? 1),
            ));
        }

        $style = ['space_after' => 0] + $options;

        foreach ($items as $index => $item) {
            // The list's space comes after its last item.
            $last = $depth === 0 && $index === array_key_last($items) && $item['items'] === [];
            $paragraph = $this->paragraphStyle($last ? ['space_after' => $options['space_after'] ?? 2] + $style : $style);
            $run = $container->addListItemRun(min($depth, WordList::LEVELS - 1), $this->listStyle, $paragraph);
            $this->writeRuns($run, $item['runs'], $style);

            if ($item['items'] !== []) {
                $this->listBlock($container, $item['items'], $options, $depth + 1);
            }
        }
    }

    private function writeRuns(TextRun $run, array $runs, array $style): void
    {
        foreach ($runs as $part) {
            $text = $this->text($part['text'] ?? '', ! empty($part['ltr']) || ! empty($style['ltr']));
            $link = is_string($part['link'] ?? null) ? HtmlSanitizer::href($part['link']) : null;
            // Links are underlined, in Word's link blue unless the run has a colour, as in the PDF.
            $font = $this->fontStyle($style, $link === null ? $part : ['underline' => true] + $part + ['color' => '#0563C1'], $text);
            $lines = preg_split('/\R/u', $text);

            foreach ($lines as $i => $line) {
                if ($i > 0) {
                    $run->addTextBreak();
                }

                if (! $this->pageFields) {
                    $this->addText($run, $line, $font, $link);

                    continue;
                }

                // Page numbers in a header or footer.
                foreach (preg_split('/(\{page\}|\{pages\})/', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $piece) {
                    match ($piece) {
                        '{page}' => $run->addField('PAGE', [], [], null, $font),
                        '{pages}' => $run->addField('NUMPAGES', [], [], null, $font),
                        default => $this->addText($run, $piece, $font, $link),
                    };
                }
            }
        }
    }

    /** Text, or a link when the run has one. */
    private function addText(TextRun $run, string $text, array $font, ?string $link): void
    {
        $link === null ? $run->addText($text, $font) : $run->addLink($link, $text, $font);
    }

    /**
     * A rich-text block: cleaned by the sanitiser, then written by
     * PhpWord's HTML parser through WordHtml. If the parser fails, the
     * block's text is written instead, so one block never stops the file.
     */
    private function richText(AbstractContainer $container, array $block, float $width): void
    {
        $doc = $this->doc;
        // The HTML comes from users: only data URIs and URLs, as in the PDF.
        $image = fn (string $src): ?string => preg_match('#^(https?://|data:)#i', $src) ? $doc->image($src) : null;
        $html = trim((new HtmlSanitizer($image, $doc->warn(...), $width / self::TWIPS_PER_MM))->sanitize($block['html']));

        if ($html === '') {
            return;
        }

        $this->registerHeadingStyles();
        $size = empty($block['options']['font_size']) ? null : (float) $block['options']['font_size'];
        $writer = new WordHtml(
            $this->rtl,
            fn (string $text): string => $this->text($text),
            function (Font $font, string $text, bool $body) use ($size): void {
                // Arabic runs use Word's complex-script properties, as in fontStyle().
                if (preg_match('/\p{Arabic}/u', $text)) {
                    $font->setRTL(true);
                    $font->setLang(new Language(null, null, $this->arabicLocale()));
                }

                if ($body && $size !== null && $font->getSize() === null) {
                    $font->setSize($size);
                }
            },
            [
                'border' => Color::css($doc->theme('border'), '#E5E7EB'),
                'header_background' => Color::css($doc->theme('primary'), '#0F766E'),
            ],
            $width / 20,
        );
        $before = count($container->getElements());

        try {
            $writer->write($container, $html);
        } catch (Throwable $e) {
            (new ReflectionProperty(AbstractContainer::class, 'elements'))->setValue($container, array_slice($container->getElements(), 0, $before));
            $doc->warn('easy-pdf-word: rich text could not be written to Word, so its text was written instead: '.mb_strimwidth($e->getMessage(), 0, 200, '...'));
            $this->plainText($container, $block);
        }
    }

    /** The paragraph styles PhpWord's HTML parser gives h1 to h4, once per file. */
    private function registerHeadingStyles(): void
    {
        if ($this->headingStyles) {
            return;
        }

        $this->headingStyles = true;

        foreach (self::HEADING_SIZES as $level => $size) {
            $color = $level === 1 ? $this->color($this->doc->theme('primary')) : null;
            $this->word->addFontStyle('Heading'.$level, ['bold' => true, 'size' => $size] + ($color ? ['color' => $color] : []), $this->paragraphStyle(['space_after' => 4, 'keep_next' => true]));
        }
    }

    /**
     * A rich-text block as plain paragraphs: its text, cleaned by the
     * sanitiser, without formatting or images.
     */
    private function plainText(AbstractContainer $container, array $block): void
    {
        $style = empty($block['options']['font_size']) ? [] : ['size' => (float) $block['options']['font_size']];
        $text = BlockText::richText($block['html'], $this->doc->warn(...));

        foreach ($text === '' ? [] : explode("\n\n", $text) as $paragraph) {
            $this->paragraph($container, [['text' => $paragraph]], $style);
        }
    }

    private function table(AbstractContainer $container, array $block, float $width): void
    {
        $options = $block['options'] + [
            'header' => false,
            'header_background' => $this->doc->theme('primary', '#0F766E'),
            'header_color' => '#FFFFFF',
            'borders' => true,
            'border_color' => $this->doc->theme('border', '#E5E7EB'),
            'border_width' => null,
            'striped' => null,
            'footer' => false,
            'font_size' => null,
            'columns' => [],
            'padding' => null,
            'valign' => null,
            'keep_together' => false,
        ];

        $rows = array_map(DocumentBuilder::rowParts(...), $block['rows']);
        $grid = DocumentBuilder::grid(array_column($rows, 0));
        $count = max(array_map(fn ($row) => max(array_map(fn ($entry) => $entry['col'] + $entry['span'], $row) ?: [0]), $grid) ?: [1]);
        $columns = $this->columnWidths($options['columns'], max(1, $count), $width);
        $aligns = array_map(fn ($c) => is_array($c) ? ($c['align'] ?? 'start') : 'start', array_values($options['columns']));

        // Border sizes are in eighths of a point; 6 is the 1px of the PDF.
        $size = isset($options['border_width']) ? max(2, min(96, (int) round((float) $options['border_width'] * 8))) : 6;
        $borderColor = $this->color($options['border_color']);
        $border = match (DocumentBuilder::borders($options['borders'])) {
            'grid' => ['borderSize' => $size, 'borderColor' => $borderColor],
            'rows' => ['borderBottomSize' => $size, 'borderBottomColor' => $borderColor],
            default => [],
        };
        $margin = is_numeric($options['padding']) ? (int) round((float) $options['padding'] * self::TWIPS_PER_MM) : null;

        /** @var Table $table */
        $table = $container->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
            'layout' => TableStyle::LAYOUT_FIXED,
            'cellMarginTop' => $margin ?? 60,
            'cellMarginBottom' => $margin ?? 60,
            'cellMarginLeft' => $margin ?? 100,
            'cellMarginRight' => $margin ?? 100,
        ] + ($this->rtl ? ['bidiVisual' => true] : []));

        $last = count($rows) - 1;
        // Continuation cells take the look of the cell they continue.
        $merged = [];

        foreach ($rows as $index => [, $rowOptions]) {
            $isHeader = $options['header'] && $index === 0;
            $isFooter = $options['footer'] && $index === $last && ! $isHeader;
            $isTotal = $rowOptions['total'] && ! $isHeader;
            $table->addRow(null, $isHeader ? ['tblHeader' => true, 'cantSplit' => true] : ['cantSplit' => true]);

            foreach ($grid[$index] as ['cell' => $cell, 'col' => $col, 'span' => $span, 'rowspan' => $rowspan, 'continue' => $continue]) {
                $cellWidth = array_sum(array_slice($columns, $col, $span));

                if ($continue) {
                    $table->addCell((int) $cellWidth, ['vMerge' => 'continue'] + ($merged[$col] ?? []));

                    continue;
                }

                // The cell's own style, then the row's, then the table's.
                $style = $cell + $rowOptions['style'] + ['align' => $aligns[$col] ?? 'start', 'size' => $options['font_size']];
                $background = $style['background'] ?? null;

                if ($isHeader) {
                    $style += ['bold' => true, 'color' => $options['header_color']];
                    $background ??= $options['header_background'];
                } elseif ($isFooter || $isTotal) {
                    $style += ['bold' => true];
                } elseif ($options['striped'] && $index % 2 === 0) {
                    $background ??= $options['striped'];
                }

                // Word keeps a row with the next one when its paragraphs are
                // kept with the next; a table kept together is every row but the last.
                if ($rowOptions['keep_with_next'] || (! empty($options['keep_together']) && $index < $last)) {
                    $style['keep_next'] = true;
                }

                $cellStyle = $border + ['valign' => match ($style['valign'] ?? $options['valign']) {
                    'middle' => 'center',
                    'bottom' => 'bottom',
                    default => 'top',
                }];

                if ($span > 1) {
                    $cellStyle['gridSpan'] = $span;
                }

                if ($background = $this->color($background)) {
                    $cellStyle['bgColor'] = $background;
                }

                if ($boxColor = $this->color($style['border'] ?? null)) {
                    // A box replaces the table's borders.
                    $cellStyle = array_diff_key($cellStyle, $border) + ['borderSize' => 6, 'borderColor' => $boxColor];
                } elseif ($isTotal) {
                    $cellStyle += ['borderTopSize' => 8, 'borderTopColor' => $borderColor ?? 'E5E7EB'];
                }

                if (is_numeric($style['padding'] ?? null)) {
                    $padding = (int) round((float) $style['padding'] * self::TWIPS_PER_MM);
                    $cellStyle += ['paddingTop' => $padding, 'paddingBottom' => $padding, 'paddingLeft' => $padding, 'paddingRight' => $padding];
                }

                if ($rowspan > 1) {
                    $merged[$col] = $cellStyle;
                    $cellStyle['vMerge'] = 'restart';
                }

                $wordCell = $table->addCell((int) $cellWidth, $cellStyle);
                $this->cellContent($wordCell, $cell, $style);
                $sideMargins = isset($cellStyle['paddingLeft']) ? 2 * $cellStyle['paddingLeft'] : 2 * ($margin ?? 100);
                $this->overlay($wordCell, $cell, ($cellWidth - $sideMargins) / 20);
            }
        }

        $container->addText('', ['size' => 4], ['spaceAfter' => 0, 'spaceBefore' => 0]);
    }

    /**
     * A cell's "overlay": a stamp floating behind the text, anchored in the
     * cell's first paragraph and placed from the cell's start-top corner.
     *
     * @param  float  $textWidth  the width of the cell's text, in points
     */
    private function overlay(AbstractContainer $wordCell, array $cell, float $textWidth): void
    {
        $overlay = $cell['overlay'] ?? null;

        if (! is_array($overlay) || ! is_string($overlay['image'] ?? null) || $overlay['image'] === '') {
            return;
        }

        $source = $this->doc->image($overlay['image']);
        $bytes = $source === null ? null : WordImage::load($source);
        $box = $bytes === null ? null : Stamp::measure($overlay, $bytes);

        if ($box === null) {
            if ($source !== null) {
                $this->doc->warn('easy-pdf-word: a cell overlay must be a PNG, JPEG, GIF, WebP or BMP image inside its cell; it was left out.');
            }

            return;
        }

        $width = $box['width'] * self::POINTS_PER_MM;
        $start = $box['start'] * self::POINTS_PER_MM;
        $first = $wordCell->getElements()[0] ?? null;
        $paragraph = $first instanceof TextRun ? $first : $wordCell->addTextRun();

        $paragraph->addImage($bytes, [
            'width' => round($width, 2),
            'height' => round($box['height'] * self::POINTS_PER_MM, 2),
            'wrappingStyle' => 'behind',
            'positioning' => 'relative',
            'posHorizontal' => 'absolute',
            'posHorizontalRel' => 'column',
            'posVertical' => 'absolute',
            'posVerticalRel' => 'text',
            // Measured from the cell's left edge: the start side is mirrored in RTL.
            'marginLeft' => round($this->rtl ? $textWidth - $start - $width : $start, 2),
            'marginTop' => round($box['down'] * self::POINTS_PER_MM, 2),
        ]);
    }

    private function cellContent(AbstractContainer $cell, array $content, array $style): void
    {
        if (! empty($content['image'])) {
            $this->image($cell, $this->doc->image($content['image']), (float) ($content['width'] ?? 30), $style['align'] ?? 'start');

            return;
        }

        if (! empty($content['qr'])) {
            $this->image($cell, Qr::dataUri($content['qr']), (float) ($content['width'] ?? 30), $style['align'] ?? 'start');

            return;
        }

        $lines = $content['lines'] ?? [$content['text'] ?? ''];
        $paragraphStyle = $style + ['space_after' => 0];

        foreach ($lines as $line) {
            if (is_array($line) && ! empty($line['image'])) {
                $this->image($cell, $this->doc->image($line['image']), (float) ($line['width'] ?? 30), $line['align'] ?? $style['align'] ?? 'start');

                continue;
            }

            $runs = match (true) {
                is_array($line) && array_is_list($line) => array_map(fn ($run) => is_array($run) ? $run : ['text' => (string) $run], $line),
                is_array($line) => [$line],
                // A cell's own "link" makes its text a link.
                default => [['text' => (string) $line] + array_intersect_key($content, ['link' => true])],
            };
            $this->paragraph($cell, $runs, isset($line['align']) ? ['align' => $line['align']] + $paragraphStyle : $paragraphStyle);
        }
    }

    private function image(AbstractContainer $container, ?string $source, float $widthMm, string $align): void
    {
        if (! $source) {
            return;
        }

        $source = WordImage::load($source);

        if ($source === null) {
            return;
        }

        // An image paragraph is not marked right to left, so "start" and
        // "end" are given as the physical side.
        $container->addImage($source, [
            'width' => round($widthMm * self::POINTS_PER_MM),
            'alignment' => match ($align) {
                'center' => Jc::CENTER,
                'end' => $this->rtl ? Jc::LEFT : Jc::RIGHT,
                default => $this->rtl ? Jc::RIGHT : Jc::LEFT,
            },
        ]);
    }

    /**
     * Header or footer text; {page} and {pages} become Word page fields.
     */
    private function pageText(AbstractContainer $container, string $html): void
    {
        // Styles and scripts are not text.
        $html = preg_replace('/<(style|script|head|title)\b[^>]*>.*?<\/\1\s*>/is', '', $html) ?? $html;
        // <bdo dir="ltr"> values ($doc->ltr()) keep their order as left-to-right overrides.
        $html = preg_replace('/<bdo dir="ltr">(.*?)<\/bdo>/is', "\u{202D}\$1\u{202C}", $html) ?? $html;
        $text = trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/tr)\b[^>]*>/i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\s*\n\s*/u', '   ', $text);
        $font = ['size' => 8, 'color' => $this->color($this->doc->theme('muted', '#6B7280'))];

        // A named paragraph style carries the font, so the page numbers Word
        // fills in get the same small grey text.
        $this->word->addFontStyle('EasyPageText', $font, $this->paragraphStyle(['align' => 'center']));
        $run = $container->addTextRun('EasyPageText');

        foreach (preg_split('/(\{page\}|\{pages\})/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
            match ($part) {
                '{page}' => $run->addField('PAGE', [], [], null, $font),
                '{pages}' => $run->addField('NUMPAGES', [], [], null, $font),
                default => $run->addText($this->text($part), $this->fontStyle($font, [], $part)),
            };
        }
    }

    private function paragraphStyle(array $style): array
    {
        $paragraph = [
            'bidi' => $this->rtl,
            'alignment' => $this->alignment($style['align'] ?? 'start'),
            'spaceAfter' => (int) (($style['space_after'] ?? 2) * self::TWIPS_PER_MM),
            'spaceBefore' => 0,
        ];

        if (isset($style['line_height'])) {
            $paragraph['lineHeight'] = (float) $style['line_height'];
        }

        if (! empty($style['keep_next'])) {
            $paragraph['keepNext'] = true;
        }

        return $paragraph;
    }

    private function fontStyle(array $paragraph, array $run, string $text): array
    {
        $style = $run + $paragraph;
        $font = [];

        if (! empty($style['bold'])) {
            $font['bold'] = true;
        }

        if (! empty($style['italic'])) {
            $font['italic'] = true;
        }

        if (! empty($style['size'])) {
            $font['size'] = (float) $style['size'];
        }

        if (! empty($style['color'])) {
            $font['color'] = $this->color($style['color']);
        }

        if (! empty($style['underline'])) {
            $font['underline'] = Font::UNDERLINE_SINGLE;
        }

        if (! empty($style['strike'])) {
            $font['strikethrough'] = true;
        }

        // Run shading takes any colour; Word's highlight takes only its 16 names.
        // A cell's or paragraph's background is not the run's.
        if ($background = $this->color($run['background'] ?? null)) {
            $font['bgColor'] = $background;
        }

        if ($name = $this->fontName($style['font'] ?? null)) {
            $font['name'] = $name;
        }

        // Arabic runs use Word's complex-script properties (font, size, bold).
        if (preg_match('/\p{Arabic}/u', $text)) {
            $font['rtl'] = true;
            $font['lang'] = new Language(null, null, $this->arabicLocale());
        }

        return $font;
    }

    /**
     * A registered font is not on the reader's computer: Word gets the name
     * it was registered with for Word ("word"), or the Word font. Other names
     * are kept, as fonts installed with Word.
     */
    private function fontName(mixed $font): ?string
    {
        if (! is_string($font) || $font === '') {
            return null;
        }

        if ($this->fonts?->has($font)) {
            return $this->fonts->get($font)['word'] ?? ($this->config['font'] ?? 'Arial');
        }

        return $font;
    }

    /**
     * Digits per the numerals setting. In right-to-left documents an "ltr"
     * run and a negative number are wrapped in a left-to-right override, so
     * Word shows "-2.3", not "2.3-", and keeps INV-٢٠٢٦-١٠٢٤ in order (an
     * embedding is not enough for Arabic-Indic digits).
     */
    private function text(string $text, bool $ltr = false): string
    {
        // Word picks the font on the reader's machine, so the separators stay "," and ".".
        $text = $this->numerals === Numerals::ARABIC ? Numerals::toArabic($text, separators: false) : $text;

        if (! $this->rtl) {
            return $text;
        }

        if ($ltr) {
            return "\u{202D}".$text."\u{202C}";
        }

        return preg_replace('/(?<![\p{L}\p{N}])(?<![\p{N}] )- ?[\d٠-٩][\d٠-٩.,٫٬]*/u', "\u{202D}\$0\u{202C}", $text) ?? $text;
    }

    /** ar-EG for an "ar_EG" document, ar-SA for plain "ar". */
    private function arabicLocale(): string
    {
        $parts = preg_split('/[-_]/', $this->doc->locale);

        return strtolower($parts[0]) === 'ar' && isset($parts[1]) ? 'ar-'.strtoupper($parts[1]) : 'ar-SA';
    }

    private function alignment(string $align): string
    {
        return match ($align) {
            'end' => Jc::END,
            'center' => Jc::CENTER,
            'justify' => Jc::BOTH,
            default => Jc::START,
        };
    }

    private function color(mixed $color): ?string
    {
        return Color::hex($color);
    }

    private function sectionStyle(PdfOptions $options): array
    {
        [$width, $height] = $options->paperSize();
        [$top, $right, $bottom, $left] = $options->margins;

        return [
            'pageSizeW' => (int) round($width * self::TWIPS_PER_MM),
            'pageSizeH' => (int) round($height * self::TWIPS_PER_MM),
            'orientation' => $options->isLandscape() ? 'landscape' : 'portrait',
            'marginTop' => (int) round($top * self::TWIPS_PER_MM),
            'marginRight' => (int) round($right * self::TWIPS_PER_MM),
            'marginBottom' => (int) round($bottom * self::TWIPS_PER_MM),
            'marginLeft' => (int) round($left * self::TWIPS_PER_MM),
            'headerHeight' => (int) round(($options->headerDistance ?? min(8, $top / 2)) * self::TWIPS_PER_MM),
            'footerHeight' => (int) round(($options->footerDistance ?? min(8, $bottom / 2)) * self::TWIPS_PER_MM),
        ];
    }

    private function contentWidth(PdfOptions $options): float
    {
        [$width] = $options->paperSize();

        return ($width - $options->margins[1] - $options->margins[3]) * self::TWIPS_PER_MM;
    }

    /** @return float[] twips per grid column */
    private function columnWidths(array $columns, int $count, float $width): array
    {
        $percents = array_map(fn ($c) => (float) (is_array($c) ? ($c['width'] ?? 0) : $c), array_values($columns));
        $percents = array_pad(array_slice($percents, 0, $count), $count, 0.0);
        $given = array_sum($percents);
        $missing = count(array_filter($percents, fn ($p) => $p <= 0));
        $rest = $missing > 0 ? max(0, 100 - $given) / $missing : 0;

        return array_map(fn ($p) => ($p > 0 ? $p : $rest) / 100 * $width, $percents);
    }
}
