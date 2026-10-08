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
 */
class DocumentBuilder
{
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
     * @param  array<int, array>  $rows  rows of cells
     * @param  array  $options  columns (percent widths, or ['width' => 30, 'align' => 'end']),
     *                          header (first row is a header), header_background, header_color,
     *                          borders (bool), border_color, striped (#hex), footer (last row bold),
     *                          font_size (pt)
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

    /** @return array<int, array> */
    private function runs(string|array $text): array
    {
        if (is_string($text)) {
            return [['text' => $text]];
        }

        return array_map(fn ($run) => is_array($run) ? $run : ['text' => (string) $run], array_values($text));
    }
}
