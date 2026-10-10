<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\DocContext;

/*
| One layout for the PDF and the Word file.
*/

return function (DocumentBuilder $cn, array $data, DocContext $doc): void {
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $border = $doc->theme('border', '#E5E7EB');
    $type = $data['type'];
    $note = $data['note'];
    $invoice = $data['invoice'];
    $seller = $data['seller'];
    $buyer = $data['buyer'];
    $totals = $data['totals'];
    $currency = $doc->currency($note['currency']);
    $decimals = $doc->decimals($note['currency']);
    $date = fn ($value) => Dates::parse($value)->format('Y/m/d');
    $logo = $doc->theme('logo');

    // Seller, title and note details.
    $cn->table([[
        ['lines' => array_values(array_filter([
            $logo ? ['image' => $logo, 'width' => 30] : null,
            ['text' => $seller['name'] ?? '', 'bold' => true, 'size' => 14, 'color' => $primary],
            ! empty($seller['address']) ? ['text' => $seller['address'], 'color' => $muted] : null,
            ! empty($seller['tax_number']) ? [['text' => $doc->t('tax_number').': ', 'color' => $muted], ['text' => $seller['tax_number'], 'color' => $muted, 'ltr' => true]] : null,
        ]))],
        ['lines' => [
            ['text' => $doc->t('title.'.$type), 'bold' => true, 'size' => 20, 'color' => $primary],
            [$doc->t('number').': ', ['text' => (string) $note['number'], 'ltr' => true]],
            $doc->t('date').': '.$date($note['date']),
        ]],
    ]], ['columns' => [55, 45], 'borders' => false]);

    $cn->spacer(4);

    // The customer, and the invoice this note corrects.
    $box = fn (string $heading, array $lines) => [
        'lines' => array_merge([['text' => $doc->t($heading), 'color' => $muted, 'size' => 9]], array_values(array_filter($lines))),
        'border' => $border,
    ];

    $cn->table([[
        $box('buyer', [
            ['text' => $buyer['name'], 'bold' => true, 'size' => 12],
            ! empty($buyer['address']) ? $buyer['address'] : null,
            ! empty($buyer['tax_number']) ? [$doc->t('tax_number').': ', ['text' => $buyer['tax_number'], 'ltr' => true]] : null,
        ]),
        $box('original_invoice', [
            [['text' => $doc->t('invoice_number').': '], ['text' => (string) $invoice['number'], 'bold' => true, 'ltr' => true]],
            ! empty($invoice['date']) ? $doc->t('invoice_date').': '.$date($invoice['date']) : null,
        ]),
    ]], ['columns' => [60, 40], 'borders' => false]);

    $cn->spacer(2);
    $cn->paragraph([['text' => $doc->t('reason').': ', 'bold' => true], $data['reason']]);

    // Items. The discount column appears only when used.
    $hasDiscount = collect($data['items'])->contains(fn ($item) => ! empty($item['discount']));
    $end = fn (string $text) => ['text' => $text, 'align' => 'end'];

    $header = ['#', $doc->t('description'), $end($doc->t('quantity')), $end($doc->t('unit_price'))];
    $columns = [5, $hasDiscount ? 42 : 54, ['width' => 10, 'align' => 'end'], ['width' => 15, 'align' => 'end']];

    if ($hasDiscount) {
        $header[] = $end($doc->t('discount'));
        $columns[] = ['width' => 12, 'align' => 'end'];
    }

    $header[] = $end($doc->t('line_total'));
    $columns[] = ['width' => 16, 'align' => 'end'];
    $rows = [$header];

    foreach ($data['items'] as $i => $item) {
        $quantity = (float) $item['quantity'];
        $row = [
            (string) ($i + 1),
            $item['description'],
            $doc->numberText($quantity, floor($quantity) == $quantity ? 0 : 2),
            $doc->numberText($item['unit_price'], $decimals),
        ];

        if ($hasDiscount) {
            $row[] = ! empty($item['discount']) ? $doc->numberText($item['discount'], $decimals) : '-';
        }

        $row[] = $doc->numberText($item['total'], $decimals);
        $rows[] = $row;
    }

    $cn->table($rows, ['header' => true, 'striped' => '#F9FAFB', 'font_size' => 9.5, 'columns' => $columns]);

    // Totals.
    $sum = [['', $doc->t('subtotal'), $doc->numberText($totals['subtotal'], $decimals)]];

    if ($totals['discount'] > 0) {
        $sum[] = ['', $doc->t('discount'), '-'.$doc->numberText($totals['discount'], $decimals)];
        $sum[] = ['', $doc->t('taxable'), $doc->numberText($totals['taxable'], $decimals)];
    }

    if ($totals['tax'] > 0) {
        $sum[] = ['', $doc->t('vat', ['rate' => $doc->rate($note['tax_rate'])]), $doc->numberText($totals['tax'], $decimals)];
    }

    $grand = ['bold' => true, 'size' => 11.5, 'color' => '#FFFFFF', 'background' => $primary];
    $sum[] = ['', ['text' => $doc->t('total')] + $grand, ['text' => $doc->numberText($totals['total'], $decimals).' '.$currency] + $grand];

    $cn->table($sum, ['columns' => [52, 28, ['width' => 20, 'align' => 'end']], 'borders' => false, 'font_size' => 10]);

    $words = $doc->inWords($totals['total'], $note['currency']);

    if ($words !== '') {
        $cn->paragraph($words, ['color' => $muted, 'size' => 9.5]);
    }

    $cn->spacer(2);
    $cn->paragraph($doc->t('effect.'.$type), ['bold' => true, 'size' => 10]);

    if (! empty($data['qr'])) {
        $cn->spacer(2);
        $cn->qr($data['qr'], 28);
        $cn->paragraph($doc->t('verify'), ['size' => 8, 'color' => $muted]);
    }

    if (! empty($data['notes'])) {
        $cn->spacer(2);
        $cn->paragraph([['text' => $doc->t('notes').': ', 'bold' => true], $data['notes']], ['size' => 9.5]);
    }
};
