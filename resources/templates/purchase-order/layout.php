<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\DocContext;

/*
| One layout for the PDF and the Word file.
*/

return function (DocumentBuilder $po, array $data, DocContext $doc): void {
    $company = (array) $doc->theme('company', []);
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $border = $doc->theme('border', '#E5E7EB');
    $order = $data['order'];
    $supplier = $data['supplier'];
    $delivery = (array) ($data['delivery'] ?? []);
    $totals = $data['totals'];
    $currency = $doc->currency($order['currency']);
    $decimals = $doc->decimals($order['currency']);
    $date = fn ($value) => Dates::parse($value)->format('Y/m/d');
    $logo = $doc->theme('logo');

    // Company, title and order details.
    $details = [
        ['text' => $doc->t('title'), 'bold' => true, 'size' => 20, 'color' => $primary],
        [$doc->t('number').': ', ['text' => (string) $order['number'], 'ltr' => true]],
        $doc->t('date').': '.$date($order['date']),
    ];

    if (! empty($order['delivery_date'])) {
        $details[] = ['text' => $doc->t('delivery_date').': '.$date($order['delivery_date']), 'bold' => true];
    }

    if (! empty($order['reference'])) {
        $details[] = [$doc->t('reference').': ', ['text' => $order['reference'], 'ltr' => true]];
    }

    $po->table([[
        ['lines' => array_values(array_filter([
            $logo ? ['image' => $logo, 'width' => 30] : null,
            ['text' => $company['name'] ?? '', 'bold' => true, 'size' => 14, 'color' => $primary],
            ! empty($company['address']) ? ['text' => $company['address'], 'color' => $muted] : null,
            ! empty($company['phone']) ? ['text' => $company['phone'], 'color' => $muted, 'ltr' => true] : null,
            ! empty($company['tax_number']) ? [['text' => $doc->t('tax_number').': ', 'color' => $muted], ['text' => $company['tax_number'], 'color' => $muted, 'ltr' => true]] : null,
        ]))],
        ['lines' => $details],
    ]], ['columns' => [55, 45], 'borders' => false]);

    $po->spacer(4);

    // Supplier, and where to deliver (the company's address when not given).
    $party = function (string $heading, array $lines) use ($doc, $muted, $border) {
        return ['lines' => array_merge([['text' => $doc->t($heading), 'color' => $muted, 'size' => 9]], array_values(array_filter($lines))), 'border' => $border];
    };

    $deliverTo = ($delivery['address'] ?? null) ?: ($company['address'] ?? null);

    $po->table([[
        $party('supplier', [
            ['text' => $supplier['name'], 'bold' => true, 'size' => 12],
            ! empty($supplier['contact']) ? $doc->t('contact').': '.$supplier['contact'] : null,
            ! empty($supplier['address']) ? $supplier['address'] : null,
            ! empty($supplier['phone']) ? ['text' => $supplier['phone'], 'ltr' => true] : null,
            ! empty($supplier['tax_number']) ? [$doc->t('tax_number').': ', ['text' => $supplier['tax_number'], 'ltr' => true]] : null,
        ]),
        $party('deliver_to', [
            $deliverTo ? ['text' => $deliverTo, 'bold' => true] : null,
            ! empty($delivery['contact']) ? $doc->t('contact').': '.$delivery['contact'] : null,
            ! empty($delivery['phone']) ? ['text' => $delivery['phone'], 'ltr' => true] : null,
        ]),
    ]], ['columns' => [50, 50], 'borders' => false]);

    $po->spacer(3);
    $po->paragraph($doc->t('intro'));

    // Items. The code and discount columns appear only when used.
    $items = $data['items'];
    $hasCode = collect($items)->contains(fn ($item) => ! empty($item['code']));
    $hasDiscount = collect($items)->contains(fn ($item) => ! empty($item['discount']));
    $end = fn (string $text) => ['text' => $text, 'align' => 'end'];

    $header = ['#'];
    $columns = [5];

    if ($hasCode) {
        $header[] = $doc->t('code');
        $columns[] = 13;
    }

    array_push($header, $doc->t('description'), $doc->t('unit'), $end($doc->t('quantity')), $end($doc->t('unit_price')));
    array_push($columns, 0, 10, ['width' => 9, 'align' => 'end'], ['width' => 14, 'align' => 'end']);

    if ($hasDiscount) {
        $header[] = $end($doc->t('discount'));
        $columns[] = ['width' => 12, 'align' => 'end'];
    }

    $header[] = $end($doc->t('line_total'));
    $columns[] = ['width' => 15, 'align' => 'end'];

    // The description takes what the other columns leave.
    $descriptionColumn = $hasCode ? 2 : 1;
    $columns[$descriptionColumn] = 100 - array_sum(array_map(fn ($column) => is_array($column) ? $column['width'] : $column, $columns));

    $rows = [$header];

    foreach ($items as $i => $item) {
        $quantity = (float) $item['quantity'];
        $row = [(string) ($i + 1)];

        if ($hasCode) {
            $row[] = ['text' => (string) ($item['code'] ?? ''), 'ltr' => true, 'size' => 8.5];
        }

        array_push($row,
            $item['description'],
            $item['unit'] ?? '',
            $doc->numberText($quantity, floor($quantity) == $quantity ? 0 : 2),
            $doc->numberText($item['unit_price'], $decimals),
        );

        if ($hasDiscount) {
            $row[] = ! empty($item['discount']) ? $doc->numberText($item['discount'], $decimals) : '-';
        }

        $row[] = $doc->numberText($item['total'], $decimals);
        $rows[] = $row;
    }

    $po->table($rows, ['header' => true, 'striped' => '#F9FAFB', 'font_size' => 9.5, 'columns' => $columns]);

    // Totals.
    $sum = [['', $doc->t('subtotal'), $doc->numberText($totals['subtotal'], $decimals)]];

    if ($totals['discount'] > 0) {
        $sum[] = ['', $doc->t('discount'), '-'.$doc->numberText($totals['discount'], $decimals)];
        $sum[] = ['', $doc->t('net'), $doc->numberText($totals['net'], $decimals)];
    }

    if ($totals['tax'] > 0) {
        $sum[] = ['', $doc->t('vat', ['rate' => $doc->rate($order['tax_rate'])]), $doc->numberText($totals['tax'], $decimals)];
    }

    $grand = ['bold' => true, 'size' => 11.5, 'color' => '#FFFFFF', 'background' => $primary];
    $sum[] = ['', ['text' => $doc->t('total')] + $grand, ['text' => $doc->numberText($totals['total'], $decimals).' '.$currency] + $grand];

    $po->table($sum, ['columns' => [52, 28, ['width' => 20, 'align' => 'end']], 'borders' => false, 'font_size' => 10]);

    $words = $doc->inWords($totals['total'], $order['currency']);

    if ($words !== '') {
        $po->paragraph($words, ['color' => $muted, 'size' => 9.5]);
    }

    // Payment terms, terms and notes.
    if (! empty($order['payment_terms'])) {
        $po->spacer(2);
        $po->paragraph([['text' => $doc->t('payment_terms').': ', 'bold' => true], $order['payment_terms']], ['size' => 9.5]);
    }

    if (! empty($data['terms'])) {
        $po->spacer(2);
        $po->heading($doc->t('terms'), 3);

        foreach (array_values($data['terms']) as $i => $term) {
            $po->paragraph(($i + 1).'. '.$term, ['size' => 9.5, 'space_after' => 1]);
        }
    }

    if (! empty($data['notes'])) {
        $po->spacer(2);
        $po->paragraph([['text' => $doc->t('notes').': ', 'bold' => true], $data['notes']], ['size' => 9.5]);
    }

    // Signature boxes: known roles are translated, anything else is printed as it is.
    if (! empty($data['signatures'])) {
        $po->spacer(6);
        $po->table([array_map(function (string $who) use ($doc, $muted) {
            $label = $doc->t('signatures.'.$who);

            return ['lines' => [
                ['text' => $label === 'signatures.'.$who ? $who : $label, 'bold' => true, 'align' => 'center'],
                ['text' => ' ', 'size' => 22],
                ['text' => '....................', 'color' => $muted, 'align' => 'center'],
            ]];
        }, array_values($data['signatures']))], ['borders' => false]);
    }
};
