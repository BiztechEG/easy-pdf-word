<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\Color;
use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\DocContext;

/*
| One layout for the PDF and the Word file.
*/

return function (DocumentBuilder $voucher, array $data, DocContext $doc): void {
    $company = (array) $doc->theme('company', []);
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $border = $doc->theme('border', '#E5E7EB');
    $type = $data['type'];
    $date = fn ($value) => Dates::parse($value)->format('Y/m/d');
    $logo = $doc->theme('logo');

    // Company and voucher title.
    $voucher->table([[
        ['lines' => array_values(array_filter([
            $logo ? ['image' => $logo, 'width' => 25] : null,
            ['text' => $company['name'] ?? '', 'bold' => true, 'size' => 13, 'color' => $primary],
            ! empty($company['address']) ? ['text' => $company['address'], 'color' => $muted, 'size' => 9] : null,
        ]))],
        ['lines' => [
            ['text' => $doc->t('title.'.$type), 'bold' => true, 'size' => 18, 'color' => $primary, 'align' => 'center'],
        ]],
        ['lines' => [
            [['text' => $doc->t('number').': ', 'size' => 9.5], ['text' => (string) $data['number'], 'size' => 9.5, 'ltr' => true]],
            ['text' => $doc->t('date').': '.$date($data['date']), 'size' => 9.5],
        ], 'align' => 'end'],
    ]], ['columns' => [32, 36, 32], 'borders' => false]);
    $voucher->line($primary);

    // The amount in a box.
    $voucher->table([[
        '',
        [
            'text' => $doc->t('amount').': '.$doc->numberText($data['amount'], $doc->decimals($data['currency'])).' '.$doc->currency($data['currency']),
            'bold' => true, 'size' => 14, 'align' => 'center', 'border' => $primary, 'background' => Color::tint($primary, 0.93),
        ],
        '',
    ]], ['columns' => [30, 40, 30], 'borders' => false]);

    // Who, how much in words, what for, how.
    $label = fn (string $key) => ['text' => $doc->t($key), 'bold' => true];
    $rows = [
        [$label('party.'.$type), $data['party']],
        [$label('amount_in_words'), $doc->inWords($data['amount'], $data['currency'])],
        [$label('for'), $data['for']],
    ];

    $method = $doc->t('methods.'.$data['method']);

    if ($data['method'] === 'cheque' && ! empty($data['cheque'])) {
        $cheque = $data['cheque'];
        $method .= ' - '.$doc->t('cheque_number').': '.($cheque['number'] ?? '');
        $method .= ! empty($cheque['bank']) ? ' - '.$doc->t('bank').': '.$cheque['bank'] : '';
        $method .= ! empty($cheque['date']) ? ' - '.$doc->t('cheque_date').': '.$date($cheque['date']) : '';
    }

    $rows[] = [$label('method'), $method];

    if (! empty($data['reference'])) {
        $rows[] = [$label('reference'), ['text' => $data['reference'], 'ltr' => true]];
    }

    $voucher->table($rows, ['columns' => [22, 78], 'border_color' => $border, 'font_size' => 11]);

    if (! empty($data['notes'])) {
        $voucher->paragraph($doc->t('notes').': '.$data['notes'], ['color' => $muted, 'size' => 9]);
    }

    // Signature boxes: known roles are translated, anything else is printed as it is.
    $label = fn (string $who) => $doc->t('signature_labels.'.$who) === 'signature_labels.'.$who ? $who : $doc->t('signature_labels.'.$who);
    $voucher->spacer(4);
    $voucher->table([array_map(fn ($who) => [
        'lines' => [
            ['text' => $label($who), 'bold' => true, 'align' => 'center'],
            ['text' => ' ', 'size' => 14],
            ['text' => '....................', 'color' => $muted, 'align' => 'center'],
        ],
    ], array_values($data['signatures']))], ['borders' => false]);
};
