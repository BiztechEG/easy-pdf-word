<?php

namespace BiztechEG\EasyPdfWord\Builder;

use BiztechEG\EasyPdfWord\Support\HtmlSanitizer;
use Closure;

/**
 * The text of builder blocks, for tests and search: one line per heading,
 * paragraph and list item, table cells joined with tabs and rows with new
 * lines, and rich text reduced to its text. Images, QR codes, spacers and
 * lines have no text.
 *
 * @internal
 */
final class BlockText
{
    /** @param  array<int, array>  $blocks */
    public static function of(array $blocks): string
    {
        $lines = [];

        foreach ($blocks as $block) {
            $text = match ($block['type'] ?? null) {
                'heading' => (string) $block['text'],
                'paragraph' => self::runs($block['runs']),
                'list' => self::items($block['items'], ! empty($block['options']['ordered'])),
                'table' => self::table($block['rows']),
                'richText' => self::richText($block['html']),
                default => null,
            };

            if ($text !== null && $text !== '') {
                $lines[] = $text;
            }
        }

        return implode("\n", $lines);
    }

    /** Rich text as plain text: paragraphs apart by a blank line, cells by a space. */
    public static function richText(string $html, ?Closure $warn = null): string
    {
        $html = (new HtmlSanitizer(fn (): ?string => null, $warn))->sanitize($html);
        $html = (string) preg_replace(['#<br/>#', '#</t[dh]>#', '#</(p|h[1-4]|li|blockquote|tr|table|ul|ol)>|<hr/>#'], ["\n", ' ', "\n\n"], $html);
        $paragraphs = [];

        foreach (preg_split('/\n{2,}/', htmlspecialchars_decode(strip_tags($html), ENT_QUOTES)) as $paragraph) {
            $paragraph = trim((string) preg_replace('/ *\n */', "\n", $paragraph));

            if ($paragraph !== '') {
                $paragraphs[] = $paragraph;
            }
        }

        return implode("\n\n", $paragraphs);
    }

    private static function runs(array $runs): string
    {
        return implode('', array_map(fn ($run) => is_array($run) ? (string) ($run['text'] ?? '') : (string) $run, $runs));
    }

    private static function items(array $items, bool $ordered, int $depth = 0): string
    {
        $lines = [];

        foreach (array_values($items) as $i => $item) {
            $lines[] = str_repeat('  ', $depth).($ordered ? ($i + 1).'. ' : '• ').self::runs($item['runs']);

            if ($item['items'] !== []) {
                $lines[] = self::items($item['items'], $ordered, $depth + 1);
            }
        }

        return implode("\n", $lines);
    }

    private static function table(array $rows): string
    {
        return implode("\n", array_map(function (array $row): string {
            [$cells] = DocumentBuilder::rowParts($row);

            return implode("\t", array_map(self::cell(...), $cells));
        }, $rows));
    }

    private static function cell(mixed $cell): string
    {
        if (! is_array($cell)) {
            return (string) $cell;
        }

        if (! isset($cell['lines'])) {
            return (string) ($cell['text'] ?? '');
        }

        $lines = array_map(fn ($line) => match (true) {
            is_array($line) && array_is_list($line) => self::runs($line),
            is_array($line) => (string) ($line['text'] ?? ''),
            default => (string) $line,
        }, (array) $cell['lines']);

        return implode(' ', array_filter($lines, fn (string $line) => $line !== ''));
    }
}
