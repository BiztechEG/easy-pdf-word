<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\DocContext;

/*
| The Word version of the report, built from the same data as pdf.blade.php.
| The table header repeats on every page in Word too.
*/

return function (DocumentBuilder $word, array $data, DocContext $doc): void {
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $border = $doc->theme('border', '#E5E7EB');
    $company = (array) $doc->theme('company', []);
    $columns = $data['columns'];
    $totals = $data['totals'];

    $format = fn (array $column, mixed $value) => match ($column['format']) {
        'number', 'money' => $value === null || $value === '' ? '' : $doc->numberText($value, $column['decimals']),
        'date' => $value ? Dates::parse($value)->format('Y/m/d') : '',
        default => $doc->text($value),
    };

    $word->table([[
        ['lines' => array_filter([
            ['text' => $data['title'], 'bold' => true, 'size' => 17, 'color' => $primary],
            ! empty($data['subtitle']) ? ['text' => $data['subtitle'], 'color' => $muted] : null,
        ])],
        ['lines' => [
            ['text' => $company['name'] ?? '', 'color' => $muted],
            ['text' => $doc->t('generated_at').': '.Dates::parse($data['generated_at'])->format('Y/m/d H:i'), 'color' => $muted, 'size' => 9],
        ], 'align' => 'end'],
    ]], ['columns' => [65, 35], 'borders' => false]);

    if (! empty($data['summary'])) {
        $cards = [];

        foreach ($data['summary'] as $label => $value) {
            $cards[] = ['lines' => [
                ['text' => (string) $label, 'color' => $muted, 'size' => 9],
                ['text' => (string) $value, 'bold' => true, 'size' => 12.5, 'color' => $primary],
            ], 'border' => $border];
        }

        $word->spacer(3);
        $word->table([$cards], ['borders' => false]);
    }

    $word->spacer(3);

    $widths = [['width' => 5]];
    $header = ['#'];

    foreach ($columns as $column) {
        $widths[] = ['align' => $column['align']];
        $header[] = ['text' => $column['label'], 'align' => $column['align']];
    }

    $rows = [$header];

    foreach ($data['rows'] as $i => $row) {
        $cells = [(string) ($i + 1)];

        foreach ($columns as $column) {
            $cells[] = $format($column, data_get($row, $column['key']));
        }

        $rows[] = $cells;
    }

    if ($data['rows'] === []) {
        $rows[] = [['text' => $doc->t('empty'), 'colspan' => count($columns) + 1, 'align' => 'center', 'color' => $muted]];
    }

    if (! empty($totals)) {
        $total = [['text' => '', 'background' => '#F3F4F6']];

        foreach ($columns as $index => $column) {
            $text = match (true) {
                array_key_exists($column['key'], $totals) => $format($column, $totals[$column['key']]),
                $index === 0 => $doc->t('total'),
                default => '',
            };
            $total[] = ['text' => $text, 'background' => '#F3F4F6'];
        }

        $rows[] = $total;
    }

    $word->table($rows, [
        'header' => true,
        'striped' => '#F9FAFB',
        'footer' => ! empty($totals),
        'font_size' => 9.5,
        'columns' => $widths,
    ]);
};
