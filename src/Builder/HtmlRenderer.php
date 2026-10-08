<?php

namespace BiztechEG\EasyPdfWord\Builder;

use BiztechEG\EasyPdfWord\Support\Color;
use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Support\Qr;

/**
 * Turns builder blocks into HTML body markup that both mPDF and Chromium
 * render the same way (tables and inline styles only).
 */
class HtmlRenderer
{
    private const HEADING_SIZES = [1 => 18, 2 => 14, 3 => 12];

    public function render(DocumentBuilder $builder, DocContext $doc): string
    {
        $html = '';

        foreach ($builder->blocks() as $block) {
            $html .= match ($block['type']) {
                'heading' => $this->heading($block, $doc),
                'paragraph' => $this->paragraph($block, $doc),
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
        return '<div style="'.$this->css($style, $doc).' margin: 0 0 3mm 0; page-break-after: avoid;">'.e($block['text']).'</div>';
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

        return '<p style="'.trim($css).'">'.$this->runs($block['runs'], $doc).'</p>';
    }

    private function runs(array $runs, DocContext $doc): string
    {
        $html = '';

        foreach ($runs as $run) {
            $css = $this->css($run, $doc, withAlign: false);
            $text = $this->text($run, $doc);
            $html .= $css === '' ? $text : '<span style="'.$css.'">'.$text.'</span>';
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
            'striped' => null,
            'footer' => false,
            'font_size' => null,
            'columns' => [],
        ];
        $columns = $this->columns($options['columns']);
        $rows = $block['rows'];
        $lastIndex = count($rows) - 1;
        $html = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 3mm;">';

        foreach ($rows as $index => $row) {
            $isHeader = $options['header'] && $index === 0;
            $isFooter = $options['footer'] && $index === $lastIndex && ! $isHeader;
            $html .= $index === 0 && $isHeader ? '<thead>' : '';
            $html .= '<tr>';
            $col = 0;

            foreach (array_values($row) as $cell) {
                $cell = is_array($cell) ? $cell : ['text' => (string) $cell];
                $column = $columns[$col] ?? [];
                $span = (int) ($cell['colspan'] ?? 1);
                $style = $cell + ['align' => $this->linesAlign($cell) ?? $column['align'] ?? 'start', 'size' => $options['font_size']];

                if ($isHeader) {
                    $style += ['bold' => true, 'color' => $options['header_color'], 'background' => $options['header_background']];
                } elseif ($isFooter) {
                    $style += ['bold' => true];
                } elseif ($options['striped'] && $index % 2 === 0) {
                    $style += ['background' => $options['striped']];
                }

                $border = match (true) {
                    Color::isValid($cell['border'] ?? null) => 'border: 1px solid '.$cell['border'].';',
                    (bool) $options['borders'] => 'border-bottom: 1px solid '.Color::css($options['border_color'], '#E5E7EB').';',
                    default => '',
                };
                $width = isset($column['width']) && $span === 1 ? 'width: '.(float) $column['width'].'%;' : '';
                $tag = $isHeader ? 'th' : 'td';

                $html .= '<'.$tag.($span > 1 ? ' colspan="'.$span.'"' : '').' style="padding: 2mm; vertical-align: top; '
                    .$border.$width.$this->css($style, $doc).'">'.$this->cellContent($cell, $doc).'</'.$tag.'>';
                $col += $span;
            }

            $html .= '</tr>';
            $html .= $index === 0 && $isHeader ? '</thead><tbody>' : '';
        }

        return $html.($options['header'] ? '</tbody>' : '').'</table>';
    }

    private function cellContent(array $cell, DocContext $doc): string
    {
        if (! empty($cell['image'])) {
            return $this->cellImage($doc->image($cell['image']), $cell);
        }

        if (! empty($cell['qr'])) {
            return '<img src="'.e(Qr::dataUri($cell['qr'])).'" style="width: '.(float) ($cell['width'] ?? 30).'mm;">';
        }

        $lines = $cell['lines'] ?? [$cell['text'] ?? ''];

        return implode('<br>', array_map(fn ($line) => match (true) {
            is_array($line) && array_is_list($line) => implode('', array_map(fn ($run) => $this->line($run, $cell, $doc), $line)),
            is_array($line) && ! empty($line['image']) => $this->cellImage($doc->image($line['image']), $line),
            is_array($line) && ! empty($line['align']) => '<div style="text-align: '.$this->align($line['align'], $doc).';'.$this->css($line, $doc, withAlign: false).'">'.$this->text($line, $doc).'</div>',
            default => $this->line($line, $cell, $doc),
        }, $lines));
    }

    private function cellImage(?string $src, array $style): string
    {
        return $src ? '<img src="'.e($src).'" style="width: '.(float) ($style['width'] ?? 30).'mm;">' : '';
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
        return is_array($line)
            ? '<span style="'.$this->css($line, $doc, withAlign: false).'">'.$this->text($line, $doc).'</span>'
            : $this->text(['text' => (string) $line] + $cell, $doc);
    }

    /**
     * Escaped text. "ltr" runs and negative numbers keep their order inside
     * right-to-left text ("-2.3", not "2.3-").
     */
    private function text(array $run, DocContext $doc): string
    {
        $text = (string) ($run['text'] ?? '');

        if (! empty($run['ltr'])) {
            return '<bdo dir="ltr">'.nl2br(e($text)).'</bdo>';
        }

        $html = nl2br(e($text));

        return $doc->isRtl()
            ? preg_replace('/(?<![\p{L}\p{N}])(?<![\p{N}] )- ?[\d٠-٩][\d٠-٩.,٫٬]*/u', '<bdo dir="ltr">$0</bdo>', $html) ?? $html
            : $html;
    }

    private function image(?string $src, array $block, DocContext $doc): string
    {
        if (! $src) {
            return '';
        }

        return '<div style="text-align: '.$this->align($block['align'], $doc).';"><img src="'.e($src).'" style="width: '
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

        return $css === [] ? '' : implode('; ', $css).';';
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
