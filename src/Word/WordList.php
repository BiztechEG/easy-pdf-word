<?php

namespace BiztechEG\EasyPdfWord\Word;

use PhpOffice\PhpWord\SimpleType\NumberFormat;

/**
 * Numbering levels for Word lists, shared by the list() block and rich text,
 * so both number and bullet their levels the same way. In a right-to-left
 * paragraph Word puts the number on the start side by itself.
 *
 * @internal
 */
final class WordList
{
    /** Word lists have nine levels. */
    public const LEVELS = 9;

    /**
     * @param  bool  $arabicDigits  numbers in Arabic-Indic digits (١. ٢. ٣.)
     * @return array{format: string, text: string, alignment: string, tabPos: int, left: int, hanging: int, start?: int}
     */
    public static function level(bool $ordered, int $depth, bool $arabicDigits = false, int $start = 1): array
    {
        $indent = 720 * ($depth + 1);
        $level = ['alignment' => 'left', 'tabPos' => $indent, 'left' => $indent, 'hanging' => 360];

        if (! $ordered) {
            return ['format' => NumberFormat::BULLET, 'text' => $depth % 2 === 1 ? '◦' : '•'] + $level;
        }

        return [
            'format' => $arabicDigits ? NumberFormat::HINDI_NUMBERS : NumberFormat::DECIMAL,
            'text' => '%'.($depth + 1).'.',
            'start' => $depth === 0 ? $start : 1,
        ] + $level;
    }

    /** A numbering style with every level ordered, or every level bulleted. */
    public static function style(bool $ordered, bool $arabicDigits = false, int $start = 1): array
    {
        return [
            'type' => $ordered ? 'multilevel' : 'hybridMultilevel',
            'levels' => array_map(fn (int $depth) => self::level($ordered, $depth, $arabicDigits, $start), range(0, self::LEVELS - 1)),
        ];
    }
}
