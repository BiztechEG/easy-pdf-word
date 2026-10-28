<?php

namespace BiztechEG\EasyPdfWord\Builder;

/**
 * A document described in code, block by block. The same blocks render to
 * PDF (through HTML) and to Word, so one description gives both formats.
 *
 *   Doc::make()
 *       ->heading('تقرير المبيعات')
 *       ->paragraph('إجمالي المبيعات: 1,250 جنيه')
 *       ->table([['الفرع', 'المبيعات'], ['القاهرة', '1,000']], ['header' => true])
 *       ->locale('ar')
 *       ->word()
 *       ->download('report.docx');
 *
 * Text styles: bold, italic, size (pt), color (#hex), align (start, end,
 * center, justify), background (#hex, table cells only), ltr (keep a phone
 * number, code or e-mail in left-to-right order inside Arabic text).
 * Paragraphs also take space_after (mm) and line_height (1.5 = one and a
 * half lines).
 *
 * A table cell is a string, or an array with "text" (or "lines" for several
 * paragraphs, "image" for a picture, "qr" for a QR code) plus any text
 * style, "colspan" and "border" (#hex, a box around the cell). A line can
 * be a string, a styled run, a list of runs, or ['image' => $path, 'width' => 30].
 *
 * A row is a list of cells, or DocumentBuilder::row($cells, $options) for a
 * row with its own style, a total row or a row kept with the next one.
 */
class DocumentBuilder
{
    private const ROW_OPTIONS = ['style', 'total', 'keep_with_next'];

    private const ROW_STYLE = ['bold', 'italic', 'size', 'color', 'background', 'align', 'border', 'padding', 'valign'];

    /** @var array<int, array> */
    private array $blocks = [];

    public function heading(string $text, int $level = 1, array $style = []): static
    {
        return $this->push('heading', ['text' => $text, 'level' => max(1, min(3, $level)), 'style' => $style]);
    }

    /**
     * @param  string|array  $text  a string, or runs: [['text' => 'المبلغ: ', 'bold' => true], '1,250']
     */
    public function paragraph(string|array $text, array $style = []): static
    {
        return $this->push('paragraph', ['runs' => $this->runs($text), 'style' => $style]);
    }

    /**
     * A numbered or bulleted list. An item is a string, a run, a list of
     * runs, or [item, [sub-items]]; sub-lists are numbered like their list.
     *
     * @param  array  $options  ordered (numbers, not bullets), start (the first number),
     *                          and the paragraph styles: size, color, bold, italic, space_after
     */
    public function list(array $items, array $options = []): static
    {
        return $this->push('list', ['items' => $this->listItems($items), 'options' => $options]);
    }

    /**
     * HTML from a rich-text editor (TipTap, CKEditor), cleaned by
     * Support\HtmlSanitizer first: headings, paragraphs, lists, quotes, links,
     * tables and images, with a short list of styles. Unlike Document::html(),
     * it is safe for HTML your users write.
     *
     * @param  array  $options  font_size (pt): the size of the block's text
     */
    public function richText(string $html, array $options = []): static
    {
        return $this->push('richText', ['html' => $html, 'options' => $options]);
    }

    /**
     * @param  array<int, array>  $rows  rows of cells, or rows from row()
     * @param  array  $options  columns (percent widths, or ['width' => 30, 'align' => 'end']),
     *                          header (first row is a header), header_background, header_color,
     *                          borders ('rows', 'grid' or 'none'; true is 'rows', false 'none'),
     *                          border_color, border_width (pt), striped (#hex), footer (last row bold),
     *                          font_size (pt), padding (mm), valign (top, middle, bottom),
     *                          keep_together (the table stays on one page)
     */
    public function table(array $rows, array $options = []): static
    {
        return $this->push('table', ['rows' => array_values($rows), 'options' => $options]);
    }

    /**
     * @param  string  $source  a file path, URL or data URI
     */
    public function image(string $source, float $widthMm = 40, string $align = 'start'): static
    {
        return $this->push('image', ['source' => $source, 'width' => $widthMm, 'align' => $align]);
    }

    public function qr(string $value, float $sizeMm = 30, string $align = 'start'): static
    {
        return $this->push('qr', ['value' => $value, 'width' => $sizeMm, 'align' => $align]);
    }

    public function spacer(float $heightMm = 5): static
    {
        return $this->push('spacer', ['height' => $heightMm]);
    }

    public function pageBreak(): static
    {
        return $this->push('pageBreak', []);
    }

    /** A thin horizontal rule. */
    public function line(?string $color = null): static
    {
        return $this->push('line', ['color' => $color]);
    }

    /**
     * A table row with options:
     *
     *   DocumentBuilder::row([['text' => 'أعمال مدنية', 'colspan' => 6]], ['style' => ['bold' => true], 'keep_with_next' => true])
     *   DocumentBuilder::row([['text' => 'الإجمالي', 'colspan' => 5], '10,200.00'], ['total' => true])
     *
     * @param  array  $cells  the row's cells, as in table()
     * @param  array  $options  style (bold, italic, size, color, background, align, border for
     *                          every cell; a cell's own style wins), total (bold, with a line
     *                          above it; several rows may be totals), keep_with_next (keep the
     *                          row on one page with the next one, as for a section heading)
     */
    public static function row(array $cells, array $options = []): array
    {
        return ['cells' => array_values($cells)] + array_intersect_key($options, array_flip(self::ROW_OPTIONS));
    }

    /**
     * A table row's cells and options. A row is read as a row() only when it
     * has a "cells" list and no keys but the row options, so rows built from
     * records keep being read as their values.
     *
     * @internal
     *
     * @return array{0: array<int, mixed>, 1: array{style: array, total: bool, keep_with_next: bool}}
     */
    public static function rowParts(array $row): array
    {
        $isRow = isset($row['cells']) && is_array($row['cells']) && array_is_list($row['cells'])
            && array_diff(array_keys($row), ['cells', ...self::ROW_OPTIONS]) === [];

        if (! $isRow) {
            return [array_values($row), ['style' => [], 'total' => false, 'keep_with_next' => false]];
        }

        return [$row['cells'], [
            'style' => is_array($row['style'] ?? null) ? array_intersect_key($row['style'], array_flip(self::ROW_STYLE)) : [],
            'total' => ! empty($row['total']),
            'keep_with_next' => ! empty($row['keep_with_next']),
        ]];
    }

    /**
     * The cells of a table placed on its grid, rows read with rowParts(). Each
     * cell gets its first column, and a cell with a rowspan leaves a
     * continuation cell at its columns in the rows below, so rows are written
     * the HTML way: without the cells a rowspan covers.
     *
     * @internal
     *
     * @param  array<int, array<int, mixed>>  $rows  the cells of each row
     * @return array<int, list<array{cell: array, col: int, span: int, rowspan: int, continue: bool}>>
     */
    public static function grid(array $rows): array
    {
        $rows = array_values($rows);
        $pending = [];
        $grid = [];

        foreach ($rows as $r => $cells) {
            $col = 0;
            $placed = [];
            $below = $pending[$r] ?? [];
            ksort($below);

            foreach ($cells as $cell) {
                // Columns taken by a rowspan from above come first.
                while (isset($below[$col])) {
                    $placed[] = $below[$col];
                    unset($below[$col]);
                    $col += $placed[array_key_last($placed)]['span'];
                }

                $cell = is_array($cell) ? $cell : ['text' => (string) $cell];
                $span = max(1, (int) ($cell['colspan'] ?? 1));
                $rowspan = max(1, min((int) ($cell['rowspan'] ?? 1), count($rows) - $r));
                $placed[] = ['cell' => $cell, 'col' => $col, 'span' => $span, 'rowspan' => $rowspan, 'continue' => false];

                for ($k = 1; $k < $rowspan; $k++) {
                    $pending[$r + $k][$col] = ['cell' => $cell, 'col' => $col, 'span' => $span, 'rowspan' => 1, 'continue' => true];
                }

                $col += $span;
            }

            // Continuations after the row's last cell.
            foreach ($below as $continuation) {
                $placed[] = $continuation;
            }

            $grid[$r] = $placed;
        }

        return $grid;
    }

    /**
     * A table's "borders": "rows" (the default, and true), "grid", or "none"
     * (false, or any empty value).
     *
     * @internal
     */
    public static function borders(mixed $borders): string
    {
        return match (true) {
            $borders === 'grid' => 'grid',
            ! $borders, $borders === 'none' => 'none',
            default => 'rows',
        };
    }

    /** @return array<int, array> */
    public function blocks(): array
    {
        return $this->blocks;
    }

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }

    private function push(string $type, array $block): static
    {
        $this->blocks[] = ['type' => $type] + $block;

        return $this;
    }

    /** @return list<array{runs: array<int, array>, items: array}> */
    private function listItems(array $items): array
    {
        return array_map(function (mixed $item): array {
            if (is_array($item) && array_is_list($item) && count($item) === 2 && is_array($item[1]) && array_is_list($item[1])) {
                return ['runs' => $this->itemRuns($item[0]), 'items' => $this->listItems($item[1])];
            }

            return ['runs' => $this->itemRuns($item), 'items' => []];
        }, array_values($items));
    }

    /** @return array<int, array> a string, one run, or a list of runs */
    private function itemRuns(mixed $item): array
    {
        return match (true) {
            is_array($item) && ! array_is_list($item) => [$item],
            is_array($item) => $this->runs($item),
            default => $this->runs((string) $item),
        };
    }

    /** @return array<int, array> */
    private function runs(string|array $text): array
    {
        if (is_string($text)) {
            return [['text' => $text]];
        }

        return array_map(fn ($run) => is_array($run) ? $run : ['text' => (string) $run], array_values($text));
    }
}
