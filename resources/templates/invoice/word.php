<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\DocContext;

/*
| The Word version of the invoice, built from the same data as pdf.blade.php.
*/

return function (DocumentBuilder $word, array $data, DocContext $doc): void {
    $invoice = $data['invoice'];
    $seller = $data['seller'];
    $buyer = $data['buyer'];
    $totals = $data['totals'];
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $border = $doc->theme('border', '#E5E7EB');
    $currency = $invoice['currency'];
    $decimals = $doc->decimals($currency);
    $currencyLabel = $doc->t('currencies.'.$currency) === 'currencies.'.$currency ? $currency : $doc->t('currencies.'.$currency);
    $date = fn ($value) => Dates::parse($value)->format('Y/m/d');
    $label = fn (string $text) => ['text' => $text, 'color' => $muted];

    // Seller name and logo, then the title and invoice details.
    $brand = array_filter([
        ['text' => $seller['name'] ?? '', 'bold' => true, 'size' => 15, 'color' => $primary],
        ! empty($seller['address']) ? $label($seller['address']) : null,
        ! empty($seller['phone']) ? $label($seller['phone']) + ['ltr' => true] : null,
    ]);

    $meta = [
        ['text' => $doc->t('title'), 'bold' => true, 'size' => 20, 'color' => $primary],
        [$doc->t('number').': ', ['text' => (string) $invoice['number'], 'ltr' => true]],
        ['text' => $doc->t('date').': '.$date($invoice['date'])],
    ];

    if ($doc->isRtl() && $doc->hasHijri()) {
        $meta[] = ['text' => $doc->t('hijri_date').': '.$doc->hijri($invoice['date'])];
    }

    if (! empty($invoice['due_date'])) {
        $meta[] = ['text' => $doc->t('due_date').': '.$date($invoice['due_date'])];
    }

    $logo = $doc->theme('logo');

    $word->table([[
        $logo ? ['image' => $logo, 'width' => 35] : ['lines' => $brand],
        ['lines' => $meta],
    ]], ['columns' => [55, 45], 'borders' => false]);

    if ($logo) {
        foreach ($brand as $line) {
            $word->paragraph([$line], ['space_after' => 0]);
        }
    }

    $word->spacer(4);

    // Seller and buyer boxes.
    $party = function (string $title, array $party) use ($doc, $label, $border) {
        $lines = [
            $label($doc->t($title)) + ['size' => 9],
            ['text' => $party['name'] ?? '', 'bold' => true, 'size' => 11.5],
        ];

        if (! empty($party['address'])) {
            $lines[] = $party['address'];
        }

        if (! empty($party['tax_number'])) {
            $lines[] = [$doc->t('tax_number').': ', ['text' => (string) $party['tax_number'], 'ltr' => true]];
        }

        if (! empty($party['commercial_register'])) {
            $lines[] = [$doc->t('commercial_register').': ', ['text' => (string) $party['commercial_register'], 'ltr' => true]];
        }

        return ['lines' => $lines, 'border' => $border];
    };

    $word->table([[$party('seller', $seller), '', $party('buyer', $buyer)]], ['columns' => [49, 2, 49], 'borders' => false]);
    $word->spacer(4);

    // Items.
    $rows = [[
        '#',
        $doc->t('description'),
        ['text' => $doc->t('quantity'), 'align' => 'end'],
        ['text' => $doc->t('unit_price'), 'align' => 'end'],
        ['text' => $doc->t('discount'), 'align' => 'end'],
        ['text' => $doc->t('line_total'), 'align' => 'end'],
    ]];

    foreach ($data['items'] as $i => $item) {
        $quantity = (float) $item['quantity'];
        $rows[] = [
            (string) ($i + 1),
            $item['description'],
            $doc->numberText($quantity, floor($quantity) == $quantity ? 0 : 2),
            $doc->numberText($item['unit_price'], $decimals),
            ! empty($item['discount']) ? $doc->numberText($item['discount'], $decimals) : '-',
            $doc->numberText($item['total'], $decimals),
        ];
    }

    $word->table($rows, [
        'header' => true,
        'striped' => '#F9FAFB',
        'font_size' => 10,
        'columns' => [6, 40, ['width' => 10, 'align' => 'end'], ['width' => 15, 'align' => 'end'], ['width' => 13, 'align' => 'end'], ['width' => 16, 'align' => 'end']],
    ]);

    // Totals, then the QR code.
    $totalRows = [['', $doc->t('subtotal'), $doc->numberText($totals['subtotal'], $decimals)]];

    if ($totals['discount'] > 0) {
        $totalRows[] = ['', $doc->t('discount'), '-'.$doc->numberText($totals['discount'], $decimals)];
    }

    $totalRows[] = ['', $doc->t('vat', ['rate' => $doc->rate($invoice['tax_rate'])]), $doc->numberText($totals['tax'], $decimals)];

    $grand = ['bold' => true, 'size' => 11.5, 'color' => '#FFFFFF', 'background' => $primary];
    $totalRows[] = ['', ['text' => $doc->t('total')] + $grand, ['text' => $doc->numberText($totals['total'], $decimals).' '.$currencyLabel] + $grand];

    $word->table($totalRows, ['columns' => [52, 28, ['width' => 20, 'align' => 'end']], 'borders' => false, 'font_size' => 10]);

    if (! empty($data['qr'])) {
        $word->qr($data['qr'], 30);

        if (! empty($invoice['eta_uuid'])) {
            $word->paragraph([$label($doc->t('eta_uuid').': ') + ['size' => 8], ['text' => $invoice['eta_uuid'], 'size' => 8, 'color' => $muted, 'ltr' => true]]);
        }
    }

    // In Arabic, or in another language when PHP's intl extension can spell it out.
    $words = $doc->inWords($totals['total'], $currency);

    if ($words !== '') {
        $word->spacer(3);
        $word->table([[[
            'lines' => [[
                'text' => $doc->t('amount_in_words').': '.$words,
            ]],
            'border' => $primary,
        ]]], ['borders' => false, 'font_size' => 10]);
    }

    if (! empty($invoice['notes'])) {
        $word->spacer(3);
        $word->paragraph($doc->t('notes'), ['bold' => true]);
        $word->paragraph($invoice['notes'], ['color' => $muted]);
    }
};
