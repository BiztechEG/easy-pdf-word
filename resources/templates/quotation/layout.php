<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\DocContext;

/*
| One layout for the PDF and the Word file.
*/

return function (DocumentBuilder $quote, array $data, DocContext $doc): void {
    $company = (array) $doc->theme('company', []);
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $border = $doc->theme('border', '#E5E7EB');
    $info = $data['quote'];
    $customer = $data['customer'];
    $totals = $data['totals'];
    $currency = $doc->currency($info['currency']);
    $decimals = $doc->decimals($info['currency']);
    $date = fn ($value) => Dates::parse($value)->format('Y/m/d');
    $logo = $doc->theme('logo');

    // Company, title and quotation details.
    $details = [
        ['text' => $doc->t('title'), 'bold' => true, 'size' => 20, 'color' => $primary],
        [$doc->t('number').': ', ['text' => (string) $info['number'], 'ltr' => true]],
        $doc->t('date').': '.$date($info['date']),
    ];

    if (! empty($info['valid_until'])) {
        $details[] = ['text' => $doc->t('valid_until').': '.$date($info['valid_until']), 'bold' => true];
    }

    $quote->table([[
        ['lines' => array_values(array_filter([
            $logo ? ['image' => $logo, 'width' => 30] : null,
            ['text' => $company['name'] ?? '', 'bold' => true, 'size' => 14, 'color' => $primary],
            ! empty($company['address']) ? ['text' => $company['address'], 'color' => $muted] : null,
            ! empty($company['phone']) ? ['text' => $company['phone'], 'color' => $muted, 'ltr' => true] : null,
        ]))],
        ['lines' => $details],
    ]], ['columns' => [55, 45], 'borders' => false]);

    $quote->spacer(4);

    // Customer.
    $to = [
        ['text' => $doc->t('to'), 'color' => $muted, 'size' => 9],
        ['text' => $customer['name'], 'bold' => true, 'size' => 12],
    ];

    if (! empty($customer['contact'])) {
        $to[] = $doc->t('contact').': '.$customer['contact'];
    }

    if (! empty($customer['address'])) {
        $to[] = $customer['address'];
    }

    if (! empty($customer['phone'])) {
        $to[] = ['text' => $customer['phone'], 'ltr' => true];
    }

    $quote->table([[['lines' => $to, 'border' => $border]]], ['borders' => false]);
    $quote->spacer(3);

    if (! empty($data['subject'])) {
        $quote->paragraph([['text' => $doc->t('subject').': ', 'bold' => true], $data['subject']]);
    }

    $quote->paragraph($doc->t('intro'));

    // Items.
    $end = fn (string $text) => ['text' => $text, 'align' => 'end'];
    $rows = [['#', $doc->t('description'), $doc->t('unit'), $end($doc->t('quantity')), $end($doc->t('unit_price')), $end($doc->t('discount')), $end($doc->t('line_total'))]];

    foreach ($data['items'] as $i => $item) {
        $quantity = (float) $item['quantity'];
        $description = ! empty($item['details'])
            ? ['lines' => [['text' => $item['description'], 'bold' => true], ['text' => $item['details'], 'color' => $muted, 'size' => 8.5]]]
            : $item['description'];

        $rows[] = [
            (string) ($i + 1),
            $description,
            $item['unit'] ?? '',
            $doc->numberText($quantity, floor($quantity) == $quantity ? 0 : 2),
            $doc->numberText($item['unit_price'], $decimals),
            ! empty($item['discount']) ? $doc->numberText($item['discount'], $decimals) : '-',
            $doc->numberText($item['total'], $decimals),
        ];
    }

    $quote->table($rows, [
        'header' => true,
        'striped' => '#F9FAFB',
        'font_size' => 9.5,
        'columns' => [5, 35, 10, ['width' => 9, 'align' => 'end'], ['width' => 14, 'align' => 'end'], ['width' => 12, 'align' => 'end'], ['width' => 15, 'align' => 'end']],
    ]);

    // Totals.
    $sum = [['', $doc->t('subtotal'), $doc->numberText($totals['subtotal'], $decimals)]];

    if ($totals['discount'] > 0) {
        $sum[] = ['', $doc->t('discount'), '-'.$doc->numberText($totals['discount'], $decimals)];
        $sum[] = ['', $doc->t('net'), $doc->numberText($totals['net'], $decimals)];
    }

    if ($totals['tax'] > 0) {
        $sum[] = ['', $doc->t('vat', ['rate' => $doc->rate($info['tax_rate'])]), $doc->numberText($totals['tax'], $decimals)];
    }

    $grand = ['bold' => true, 'size' => 11.5, 'color' => '#FFFFFF', 'background' => $primary];
    $sum[] = ['', ['text' => $doc->t('total')] + $grand, ['text' => $doc->numberText($totals['total'], $decimals).' '.$currency] + $grand];

    $quote->table($sum, ['columns' => [52, 28, ['width' => 20, 'align' => 'end']], 'borders' => false, 'font_size' => 10]);

    $words = $doc->inWords($totals['total'], $info['currency']);

    if ($words !== '') {
        $quote->paragraph($words, ['color' => $muted, 'size' => 9.5]);
    }

    // Terms, notes and signature.
    if (! empty($data['terms'])) {
        $quote->spacer(2);
        $quote->heading($doc->t('terms'), 3);

        foreach (array_values($data['terms']) as $i => $term) {
            $quote->paragraph(($i + 1).'. '.$term, ['size' => 9.5, 'space_after' => 1]);
        }
    }

    if (! empty($data['notes'])) {
        $quote->spacer(2);
        $quote->paragraph([['text' => $doc->t('notes').': ', 'bold' => true], $data['notes']], ['size' => 9.5]);
    }

    $quote->spacer(4);
    $quote->paragraph($doc->t('closing'));

    if (! empty($data['sender'])) {
        $quote->table([['', ['lines' => array_values(array_filter([
            ! empty($data['sender']['title']) ? ['text' => $data['sender']['title'], 'align' => 'center'] : null,
            ['text' => ' ', 'size' => 18],
            ['text' => $data['sender']['name'] ?? '', 'bold' => true, 'align' => 'center'],
        ]))]]], ['columns' => [60, 40], 'borders' => false]);
    }
};
