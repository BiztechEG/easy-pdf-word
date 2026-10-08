<?php

namespace BiztechEG\EasyPdfWord\Word;

use BiztechEG\EasyPdfWord\Arabic\Numerals;
use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Exceptions\DriverNotAvailable;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Support\Color;
use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Support\Qr;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Table as TableStyle;

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

    private const HEADING_SIZES = [1 => 18, 2 => 14, 3 => 12];

    private DocContext $doc;

    private PhpWord $word;

    private bool $rtl;

    private string $numerals;

    public function __construct(private array $config = []) {}

    public static function isAvailable(): bool
    {
        return class_exists(PhpWord::class);
    }

    public function render(DocumentBuilder $builder, DocContext $doc, PdfOptions $options): string
    {
        if (! self::isAvailable()) {
            throw DriverNotAvailable::missingPackage('word', 'phpoffice/phpword');
        }

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

            if ($options->header) {
                $this->pageText($section->addHeader(), $options->header);
            }

            if ($options->footer) {
                $this->pageText($section->addFooter(), $options->footer);
            }

            $contentWidth = $this->contentWidth($options);

            foreach ($builder->blocks() as $block) {
                $this->block($section, $block, $contentWidth);
            }

            $file = tempnam(sys_get_temp_dir(), 'easy-docx');
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

    private function block(AbstractContainer $container, array $block, float $width): void
    {
        match ($block['type']) {
            'heading' => $this->heading($container, $block),
            'paragraph' => $this->paragraph($container, $block['runs'], $block['style']),
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

        $this->paragraph($container, [['text' => $block['text']]], $style + ['space_after' => 4]);
    }

    private function paragraph(AbstractContainer $container, array $runs, array $style): void
    {
        $run = $container->addTextRun($this->paragraphStyle($style));

        foreach ($runs as $part) {
            $text = $this->text($part['text'] ?? '', ! empty($part['ltr']) || ! empty($style['ltr']));
            $font = $this->fontStyle($style, $part, $text);
            $lines = preg_split('/\R/u', $text);

            foreach ($lines as $i => $line) {
                if ($i > 0) {
                    $run->addTextBreak();
                }

                $run->addText($line, $font);
            }
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
            'striped' => null,
            'footer' => false,
            'font_size' => null,
            'columns' => [],
        ];

        $rows = $block['rows'];
        $count = max(array_map(fn ($row) => array_sum(array_map(fn ($cell) => (int) (is_array($cell) ? ($cell['colspan'] ?? 1) : 1), $row)), $rows) ?: [1]);
        $columns = $this->columnWidths($options['columns'], $count, $width);
        $aligns = array_map(fn ($c) => is_array($c) ? ($c['align'] ?? 'start') : 'start', array_values($options['columns']));

        $border = $options['borders'] ? ['borderBottomSize' => 6, 'borderBottomColor' => $this->color($options['border_color'])] : [];

        /** @var Table $table */
        $table = $container->addTable([
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
            'layout' => TableStyle::LAYOUT_FIXED,
            'cellMarginTop' => 60,
            'cellMarginBottom' => 60,
            'cellMarginLeft' => 100,
            'cellMarginRight' => 100,
        ] + ($this->rtl ? ['bidiVisual' => true] : []));

        $last = count($rows) - 1;

        foreach ($rows as $index => $row) {
            $isHeader = $options['header'] && $index === 0;
            $isFooter = $options['footer'] && $index === $last && ! $isHeader;
            $table->addRow(null, $isHeader ? ['tblHeader' => true, 'cantSplit' => true] : ['cantSplit' => true]);
            $col = 0;

            foreach (array_values($row) as $cell) {
                $cell = is_array($cell) ? $cell : ['text' => (string) $cell];
                $span = max(1, (int) ($cell['colspan'] ?? 1));
                $cellWidth = array_sum(array_slice($columns, $col, $span));
                $style = $cell + ['align' => $aligns[$col] ?? 'start', 'size' => $options['font_size']];
                $background = $cell['background'] ?? null;

                if ($isHeader) {
                    $style += ['bold' => true, 'color' => $options['header_color']];
                    $background ??= $options['header_background'];
                } elseif ($isFooter) {
                    $style += ['bold' => true];
                } elseif ($options['striped'] && $index % 2 === 0) {
                    $background ??= $options['striped'];
                }

                $cellStyle = $border + ['valign' => 'top'];

                if ($span > 1) {
                    $cellStyle['gridSpan'] = $span;
                }

                if ($background = $this->color($background)) {
                    $cellStyle['bgColor'] = $background;
                }

                if ($borderColor = $this->color($cell['border'] ?? null)) {
                    $cellStyle += ['borderSize' => 6, 'borderColor' => $borderColor];
                }

                $wordCell = $table->addCell((int) $cellWidth, $cellStyle);
                $this->cellContent($wordCell, $cell, $style);
                $col += $span;
            }
        }

        $container->addText('', ['size' => 4], ['spaceAfter' => 0, 'spaceBefore' => 0]);
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
                default => [['text' => (string) $line]],
            };
            $this->paragraph($cell, $runs, isset($line['align']) ? ['align' => $line['align']] + $paragraphStyle : $paragraphStyle);
        }
    }

    private function image(AbstractContainer $container, ?string $source, float $widthMm, string $align): void
    {
        if (! $source) {
            return;
        }

        if (str_starts_with($source, 'data:')) {
            $source = base64_decode(substr($source, strpos($source, ',') + 1));
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

        if (! empty($style['font'])) {
            $font['name'] = $style['font'];
        }

        // Arabic runs use Word's complex-script properties (font, size, bold).
        if (preg_match('/\p{Arabic}/u', $text)) {
            $font['rtl'] = true;
            $font['lang'] = new Language(null, null, $this->arabicLocale());
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
        $text = $this->numerals === Numerals::ARABIC ? Numerals::toArabic($text) : $text;

        if (! $this->rtl) {
            return $text;
        }

        if ($ltr) {
            return "\u{202D}".$text."\u{202C}";
        }

        return preg_replace('/(?<![\p{L}\p{N}])- ?[\d٠-٩][\d٠-٩.,٫٬]*/u', "\u{202D}\$0\u{202C}", $text) ?? $text;
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
            'headerHeight' => (int) round(min(8, $top / 2) * self::TWIPS_PER_MM),
            'footerHeight' => (int) round(min(8, $bottom / 2) * self::TWIPS_PER_MM),
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
