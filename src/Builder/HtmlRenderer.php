<?php

namespace BiztechEG\EasyPdfWord\Builder;

use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Support\Color;
use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Support\Html;
use BiztechEG\EasyPdfWord\Support\HtmlSanitizer;
use BiztechEG\EasyPdfWord\Support\Qr;
use BiztechEG\EasyPdfWord\Support\Stamp;
use DOMDocument;

/**
 * Turns builder blocks into HTML body markup that both mPDF and Chromium
 * render the same way (tables and inline styles only).
 */
class HtmlRenderer
{
    /** heading() takes levels 1 to 3; rich text has h4 too. */
    private const HEADING_SIZES = [1 => 18, 2 => 14, 3 => 12, 4 => 11];

    /** Unregistered fonts already reported through warn. */
    private array $warnedFonts = [];

    /**
     * @param  float  $contentWidth  the width of the page's text in mm, for rich-text images and tables
     * @param  FontRegistry|null  $fonts  the fonts a run's "font" may name
     */
    public function __construct(
        private readonly float $contentWidth = 180,
        private readonly ?FontRegistry $fonts = null,
    ) {}

    public function render(DocumentBuilder $builder, DocContext $doc): string
    {
        $html = '';

        foreach ($builder->blocks() as $block) {
            $html .= match ($block['type']) {
                'heading' => $this->heading($block, $doc),
                'paragraph' => $this->paragraph($block, $doc),
                'richText' => $this->richText($block, $doc),
                'table' => $this->table($block, $doc),
                'image' => $this->image($doc->image($block['source']), $block, $doc),
                'qr' => $this->image(Qr::dataUri($block['value']), $block, $doc),
                'spacer' => '<div style="height: '.(float) $block['height'].'mm;"></div>',
                'pageBreak' => '<div style="page-break-before: always;"></div>',
                'line' => '<hr style="border: 0; border-top: 1px solid '.Color::css($block['color'] ?? $doc->theme('border'), '#E5E7EB').'; margin: 3mm 0;">',
                default => '',
            };
        }

        return $html;
    }

    private function heading(array $block, DocContext $doc): string
    {
        $style = $block['style'] + [
            'size' => self::HEADING_SIZES[$block['level']],
            'bold' => true,
            'color' => $block['level'] === 1 ? $doc->theme('primary') : null,
        ];

        // A heading stays on the same page as the text after it.
        $text = empty($style['ltr']) ? Html::escape($block['text']) : '<bdo dir="ltr">'.Html::escape($block['text']).'</bdo>';

        return '<div style="'.$this->css($style, $doc).' margin: 0 0 3mm 0; page-break-after: avoid;">'.$text.'</div>';
    }

    private function paragraph(array $block, DocContext $doc): string
    {
        $style = $block['style'];
        $css = $this->css($style, $doc);

        // The same spacing as in the Word file.
        if (isset($style['space_after'])) {
            $css .= ' margin-bottom: '.(float) $style['space_after'].'mm;';
        }

        if (isset($style['line_height'])) {
            $css .= ' line-height: '.(float) $style['line_height'].';';
        }

        return '<p style="'.trim($css).'">'.$this->runs($block['runs'], $doc, ! empty($style['ltr'])).'</p>';
    }

    /**
     * Editor HTML, sanitised, in the document's direction. Headings take the
     * builder's sizes and tables the builder's look with a grid; the HTML's
     * own styles come after these, so they win.
     */
    private function richText(array $block, DocContext $doc): string
    {
        // The HTML comes from users, so it may not point at files on the server
        // (a stamp or a signature in images.paths): only data URIs and URLs,
        // with the same checks as builder images.
        $image = fn (string $src): ?string => preg_match('#^(https?://|data:)#i', $src) ? $doc->image($src) : null;
        $html = trim((new HtmlSanitizer($image, $doc->warn(...), $this->contentWidth))->sanitize($block['html']));

        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML('<root>'.$html.'</root>', LIBXML_NONET);
        $border = 'border: 1px solid '.Color::css($doc->theme('border'), '#E5E7EB');
        $defaults = [
            'h1' => 'font-size: 18pt; color: '.Color::css($doc->theme('primary'), '#0F766E').';',
            'blockquote' => 'margin: 0 0 3mm 0; padding-'.$doc->start().': 4mm; border-'.$doc->start().': 2px solid '.Color::css($doc->theme('border'), '#E5E7EB').';',
            'table' => 'width: 100%; border-collapse: collapse; margin-bottom: 3mm;',
            'td' => 'padding: 2mm; vertical-align: top; text-align: '.$doc->start().'; '.$border.';',
            'th' => 'padding: 2mm; vertical-align: top; text-align: '.$doc->start().'; '.$border.'; font-weight: bold; color: #FFFFFF; background-color: '
                .Color::css($doc->theme('primary'), '#0F766E').';',
        ];

        foreach ([2, 3, 4] as $level) {
            $defaults['h'.$level] = 'font-size: '.self::HEADING_SIZES[$level].'pt;';
        }

        foreach ([1, 2, 3, 4] as $level) {
            // A heading stays on the same page as the text after it.
            $defaults['h'.$level] = 'font-weight: bold; margin: 0 0 3mm 0; page-break-after: avoid; '.$defaults['h'.$level];
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            $css = $defaults[$element->tagName] ?? null;

            if ($css !== null) {
                $own = $element->getAttribute('style');
                $element->setAttribute('style', $own === '' ? $css : $css.' '.$own);
            }
        }

        $body = '';

        foreach ($dom->documentElement->childNodes as $node) {
            $body .= $dom->saveXML($node, LIBXML_NOEMPTYTAG);
        }

        // <br></br> is two line breaks to a browser.
        $body = (string) preg_replace('#<(br|hr|img)([^>]*)></\1>#', '<$1$2/>', $body);
        $size = empty($block['options']['font_size']) ? '' : ' font-size: '.(float) $block['options']['font_size'].'pt;';

        return '<div dir="'.$doc->direction.'" style="direction: '.$doc->direction.'; text-align: '.$doc->start().';'.$size.'">'.$body.'</div>';
    }

    /** @param  bool  $ltr  the whole paragraph is left to right, as in the Word file */
    private function runs(array $runs, DocContext $doc, bool $ltr = false): string
    {
        $html = '';

        foreach ($runs as $run) {
            $css = $this->css($run, $doc, withAlign: false);
            $text = $this->text($ltr ? ['ltr' => true] + $run : $run, $doc);
            $html .= match (true) {
                $this->href($run) !== null => $this->link($run, $text, $doc),
                $css === '' => $text,
                default => '<span style="'.$css.'">'.$text.'</span>',
            };
        }

        return $html;
    }

    private function table(array $block, DocContext $doc): string
    {
        $options = $block['options'] + [
            'header' => false,
            'header_background' => $doc->theme('primary', '#0F766E'),
            'header_color' => '#FFFFFF',
            'borders' => true,
            'border_color' => $doc->theme('border', '#E5E7EB'),
            'border_width' => null,
            'striped' => null,
            'footer' => false,
            'font_size' => null,
            'columns' => [],
            'padding' => null,
            'valign' => null,
            'keep_together' => false,
        ];
        $columns = $this->columns($options['columns']);
        $parts = array_map(DocumentBuilder::rowParts(...), $block['rows']);
        $grid = DocumentBuilder::grid(array_column($parts, 0));
        $lastIndex = count($parts) - 1;
        $borders = DocumentBuilder::borders($options['borders']);
        $line = (isset($options['border_width']) ? (float) $options['border_width'].'pt' : '1px')
            .' solid '.Color::css($options['border_color'], '#E5E7EB');
        // mPDF keeps such a table on one page; Chromium reads break-inside.
        $keep = empty($options['keep_together']) ? '' : ' page-break-inside: avoid; break-inside: avoid;';
        $html = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 3mm;'.$keep.'">';

        foreach ($parts as $index => [, $rowOptions]) {
            $isHeader = $options['header'] && $index === 0;
            $isFooter = $options['footer'] && $index === $lastIndex && ! $isHeader;
            $isTotal = $rowOptions['total'] && ! $isHeader;
            $html .= $index === 0 && $isHeader ? '<thead>' : '';
            // mPDF 8.2.4 and later keep such a row on one page with the next one.
            $html .= $rowOptions['keep_with_next'] ? '<tr style="page-break-after: avoid;">' : '<tr>';

            foreach ($grid[$index] as ['cell' => $cell, 'col' => $col, 'span' => $span, 'rowspan' => $rowspan, 'continue' => $continue]) {
                // The cell above spans this row.
                if ($continue) {
                    continue;
                }

                $column = $columns[$col] ?? [];
                // The cell's own style, then the row's, then the table's.
                $linesAlign = $this->linesAlign($cell);
                $style = $cell + ($linesAlign ? ['align' => $linesAlign] : []) + $rowOptions['style']
                    + ['align' => $column['align'] ?? 'start', 'size' => $options['font_size']];

                if ($isHeader) {
                    $style += ['bold' => true, 'color' => $options['header_color'], 'background' => $options['header_background']];
                } elseif ($isFooter || $isTotal) {
                    $style += ['bold' => true];
                } elseif ($options['striped'] && $index % 2 === 0) {
                    $style += ['background' => $options['striped']];
                }

                $box = Color::isValid($style['border'] ?? null);
                $border = match (true) {
                    $box => 'border: 1px solid '.$style['border'].';',
                    $borders === 'grid' => 'border: '.$line.';',
                    $borders === 'rows' => 'border-bottom: '.$line.';',
                    default => '',
                };

                if ($isTotal && ! $box) {
                    $border .= 'border-top: 1pt solid '.Color::css($options['border_color'], '#E5E7EB').';';
                }

                $width = isset($column['width']) && $span === 1 ? 'width: '.(float) $column['width'].'%;' : '';
                $padding = is_numeric($style['padding'] ?? $options['padding']) ? (float) ($style['padding'] ?? $options['padding']).'mm' : '2mm';
                $valign = self::valign($style['valign'] ?? $options['valign']);
                $tag = $isHeader ? 'th' : 'td';
                $attributes = ($span > 1 ? ' colspan="'.$span.'"' : '').($rowspan > 1 ? ' rowspan="'.$rowspan.'"' : '');

                $html .= '<'.$tag.$attributes.' style="padding: '.$padding.'; vertical-align: '.$valign.'; '
                    .$border.$width.$this->css($style, $doc).$this->overlay($cell, $doc).'">'.$this->cellContent($cell, $doc).'</'.$tag.'>';
            }

            $html .= '</tr>';
            $html .= $index === 0 && $isHeader ? '</thead><tbody>' : '';
        }

        return $html.($options['header'] ? '</tbody>' : '').'</table>';
    }

    private static function valign(mixed $valign): string
    {
        return in_array($valign, ['middle', 'bottom'], true) ? $valign : 'top';
    }

    /** A cell's "overlay": a stamp as the cell's background, behind its text. */
    private function overlay(array $cell, DocContext $doc): string
    {
        $overlay = $cell['overlay'] ?? null;

        if (! is_array($overlay) || ! is_string($overlay['image'] ?? null) || $overlay['image'] === '') {
            return '';
        }

        $uri = $doc->image($overlay['image']);
        $bytes = $uri === null ? false : base64_decode(substr($uri, strpos($uri, ',') + 1), true);
        $box = is_string($bytes) ? Stamp::measure($overlay, $bytes) : null;
        $stamp = $box === null ? null : Stamp::forPdf($bytes, $box, $doc->isRtl());

        if ($stamp === null) {
            if ($uri !== null) {
                $doc->warn('easy-pdf-word: a cell overlay must be a PNG, JPEG, GIF, WebP or BMP image inside its cell; it was left out.');
            }

            return '';
        }

        return ' '.Stamp::css($stamp, $doc->isRtl());
    }

    private function cellContent(array $cell, DocContext $doc): string
    {
        if (! empty($cell['image'])) {
            return $this->cellImage($doc->image($cell['image']), $cell);
        }

        if (! empty($cell['qr'])) {
            return '<img src="'.Html::escape(Qr::dataUri($cell['qr'])).'" style="width: '.(float) ($cell['width'] ?? 30).'mm;">';
        }

        $lines = $cell['lines'] ?? [$cell['text'] ?? ''];

        return implode('<br>', array_map(fn ($line) => match (true) {
            is_array($line) && array_is_list($line) => implode('', array_map(fn ($run) => $this->line($run, $cell, $doc), $line)),
            is_array($line) && ! empty($line['image']) => $this->cellImage($doc->image($line['image']), $line),
            is_array($line) && ! empty($line['align']) => '<div style="text-align: '.$this->align($line['align'], $doc).';'.$this->css($line, $doc, withAlign: false).'">'.($this->href($line) === null ? $this->text($line, $doc) : $this->link($line, $this->text($line, $doc), $doc)).'</div>',
            default => $this->line($line, $cell, $doc),
        }, $lines));
    }

    private function cellImage(?string $src, array $style): string
    {
        return $src ? '<img src="'.Html::escape($src).'" style="width: '.(float) ($style['width'] ?? 30).'mm;">' : '';
    }

    /**
     * mPDF aligns everything in a cell like the cell itself, so a cell whose
     * aligned lines all agree (all centred, say) takes that alignment.
     */
    private function linesAlign(array $cell): ?string
    {
        $aligns = array_unique(array_filter(array_map(fn ($line) => is_array($line) ? ($line['align'] ?? null) : null, $cell['lines'] ?? [])));

        return count($aligns) === 1 ? reset($aligns) : null;
    }

    private function line(mixed $line, array $cell, DocContext $doc): string
    {
        return match (true) {
            is_array($line) && $this->href($line) !== null => $this->link($line, $this->text($line, $doc), $doc),
            is_array($line) => '<span style="'.$this->css($line, $doc, withAlign: false).'">'.$this->text($line, $doc).'</span>',
            // A cell's own "link" makes its text a link.
            $this->href($cell) !== null => $this->link(['text' => (string) $line] + $cell, $this->text(['text' => (string) $line] + $cell, $doc), $doc),
            default => $this->text(['text' => (string) $line] + $cell, $doc),
        };
    }

    /** A run's "link": http, https and mailto only, as in rich text. */
    private function href(array $run): ?string
    {
        return is_string($run['link'] ?? null) ? HtmlSanitizer::href($run['link']) : null;
    }

    /**
     * A link carries the run's styles itself, because the engines give links
     * their own colour. It is underlined, in Word's link blue unless the run
     * has a colour, as in the Word file.
     */
    private function link(array $run, string $text, DocContext $doc): string
    {
        $style = ['underline' => true, 'color' => Color::css($run['color'] ?? null, '#0563C1')] + $run;

        return '<a href="'.Html::escape((string) $this->href($run)).'" style="'.$this->css($style, $doc, withAlign: false).'">'.$text.'</a>';
    }

    /**
     * Escaped text. "ltr" runs and negative numbers keep their order inside
     * right-to-left text ("-2.3", not "2.3-").
     */
    private function text(array $run, DocContext $doc): string
    {
        $text = (string) ($run['text'] ?? '');

        if (! empty($run['ltr'])) {
            return '<bdo dir="ltr">'.nl2br(Html::escape($text)).'</bdo>';
        }

        $html = nl2br(Html::escape($text));

        return $doc->isRtl()
            ? preg_replace('/(?<![\p{L}\p{N}])(?<![\p{N}] )- ?[\d٠-٩][\d٠-٩.,٫٬]*/u', '<bdo dir="ltr">$0</bdo>', $html) ?? $html
            : $html;
    }

    private function image(?string $src, array $block, DocContext $doc): string
    {
        if (! $src) {
            return '';
        }

        return '<div style="text-align: '.$this->align($block['align'], $doc).';"><img src="'.Html::escape($src).'" style="width: '
            .(float) $block['width'].'mm;"></div>';
    }

    /** @return array<int, array{width?: float, align?: string}> */
    private function columns(array $columns): array
    {
        return array_map(fn ($column) => is_array($column) ? $column : ['width' => (float) $column], array_values($columns));
    }

    private function css(array $style, DocContext $doc, bool $withAlign = true): string
    {
        $css = [];

        if ($withAlign && ! empty($style['align'])) {
            $css[] = 'text-align: '.$this->align($style['align'], $doc);
        }

        if (! empty($style['bold'])) {
            $css[] = 'font-weight: bold';
        }

        if (! empty($style['italic'])) {
            $css[] = 'font-style: italic';
        }

        if (! empty($style['size'])) {
            $css[] = 'font-size: '.(float) $style['size'].'pt';
        }

        if ($color = Color::css($style['color'] ?? null)) {
            $css[] = 'color: '.$color;
        }

        if ($background = Color::css($style['background'] ?? null)) {
            $css[] = 'background-color: '.$background;
        }

        $decoration = array_keys(array_filter(['underline' => ! empty($style['underline']), 'line-through' => ! empty($style['strike'])]));

        if ($decoration !== []) {
            $css[] = 'text-decoration: '.implode(' ', $decoration);
        }

        if ($font = $this->font($style['font'] ?? null, $doc)) {
            $css[] = "font-family: '".$font."'";
        }

        return $css === [] ? '' : implode('; ', $css).';';
    }

    /**
     * A registered font's name for CSS. Others are left out and reported
     * once: the PDF engine has no file for them.
     */
    private function font(mixed $font, DocContext $doc): ?string
    {
        if (! is_string($font) || $font === '') {
            return null;
        }

        if ($this->fonts?->has($font)) {
            return strtolower($font);
        }

        if (! isset($this->warnedFonts[$key = strtolower($font)])) {
            $this->warnedFonts[$key] = true;
            $doc->warn('easy-pdf-word: the font ['.mb_strimwidth($font, 0, 60, '...').'] is not registered, so the text is in the document\'s font.');
        }

        return null;
    }

    private function align(string $align, DocContext $doc): string
    {
        return match ($align) {
            'end' => $doc->end(),
            'center' => 'center',
            'justify' => 'justify',
            default => $doc->start(),
        };
    }
}
