<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\DocContext;
use Illuminate\Support\Carbon;

/*
| One layout for the PDF and the Word file.
*/

return function (DocumentBuilder $note, array $data, DocContext $doc): void {
    $company = (array) $doc->theme('company', []);
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $border = $doc->theme('border', '#E5E7EB');
    $delivery = $data['delivery'];
    $customer = $data['customer'];
    $shipTo = (array) ($data['ship_to'] ?? []);
    $transport = array_filter((array) ($data['transport'] ?? []));
    $date = fn ($value) => Carbon::parse($value)->format('Y/m/d');
    $quantity = fn ($value) => $doc->numberText($value, floor((float) $value) == (float) $value ? 0 : 2);
    $logo = $doc->theme('logo');

    // Company, title and references.
    $details = [
        ['text' => $doc->t('title'), 'bold' => true, 'size' => 20, 'color' => $primary],
        [$doc->t('number').': ', ['text' => (string) $delivery['number'], 'ltr' => true]],
        $doc->t('date').': '.$date($delivery['date']),
    ];

    foreach (['order_number', 'invoice_number'] as $reference) {
        if (! empty($delivery[$reference])) {
            $details[] = [$doc->t($reference).': ', ['text' => (string) $delivery[$reference], 'ltr' => true]];
        }
    }

    $note->table([[
        ['lines' => array_values(array_filter([
            $logo ? ['image' => $logo, 'width' => 30] : null,
            ['text' => $company['name'] ?? '', 'bold' => true, 'size' => 14, 'color' => $primary],
            ! empty($company['address']) ? ['text' => $company['address'], 'color' => $muted] : null,
            ! empty($company['phone']) ? ['text' => $company['phone'], 'color' => $muted, 'ltr' => true] : null,
        ]))],
        ['lines' => $details],
    ]], ['columns' => [55, 45], 'borders' => false]);

    $note->spacer(4);

    // Customer, and where the goods went (the customer's address when not given).
    $party = fn (string $heading, array $lines) => [
        'lines' => array_merge([['text' => $doc->t($heading), 'color' => $muted, 'size' => 9]], array_values(array_filter($lines))),
        'border' => $border,
    ];

    $note->table([[
        $party('customer', [
            ['text' => $customer['name'], 'bold' => true, 'size' => 12],
            ! empty($customer['address']) ? $customer['address'] : null,
            ! empty($customer['phone']) ? ['text' => $customer['phone'], 'ltr' => true] : null,
        ]),
        $party('ship_to', [
            ['text' => ($shipTo['address'] ?? null) ?: ($customer['address'] ?? ''), 'bold' => true],
            ! empty($shipTo['contact']) ? $doc->t('contact').': '.$shipTo['contact'] : null,
            ! empty($shipTo['phone']) ? ['text' => $shipTo['phone'], 'ltr' => true] : null,
        ]),
    ]], ['columns' => [50, 50], 'borders' => false]);

    $note->spacer(3);
    $note->paragraph($doc->t('intro'));

    // Items. Code, ordered, remaining and notes appear only when used.
    $items = $data['items'];
    $has = fn (string $key) => collect($items)->contains(fn ($item) => isset($item[$key]) && $item[$key] !== '' && $item[$key] !== null);
    $end = fn (string $text) => ['text' => $text, 'align' => 'end'];
    $number = ['width' => 11, 'align' => 'end'];

    $columns = ['#' => [5, fn ($item, $i) => (string) ($i + 1)]];

    if ($has('code')) {
        $columns['code'] = [13, fn ($item) => ['text' => (string) ($item['code'] ?? ''), 'ltr' => true, 'size' => 8.5]];
    }

    $columns['description'] = [0, fn ($item) => $item['description']];
    $columns['unit'] = [10, fn ($item) => $item['unit'] ?? ''];

    if ($has('ordered')) {
        $columns['ordered'] = [$number, fn ($item) => isset($item['ordered']) ? $quantity($item['ordered']) : ''];
    }

    $columns['quantity'] = [$number, fn ($item) => ['text' => $quantity($item['quantity']), 'bold' => true]];

    if ($has('remaining')) {
        $columns['remaining'] = [$number, fn ($item) => isset($item['remaining']) ? $quantity($item['remaining']) : ''];
    }

    if ($has('notes')) {
        $columns['item_notes'] = [18, fn ($item) => ['text' => (string) ($item['notes'] ?? ''), 'size' => 8.5, 'color' => $muted]];
    }

    $widths = array_map(fn ($column) => $column[0], array_values($columns));
    $descriptionColumn = array_search('description', array_keys($columns), true);
    $widths[$descriptionColumn] = 100 - array_sum(array_map(fn ($width) => is_array($width) ? $width['width'] : $width, $widths));

    $header = [];

    foreach (array_keys($columns) as $key) {
        $header[] = $key === '#' ? '#' : (in_array($key, ['ordered', 'quantity', 'remaining'], true) ? $end($doc->t($key)) : $doc->t($key));
    }

    $rows = [$header];

    foreach ($items as $i => $item) {
        $rows[] = array_map(fn ($column) => $column[1]($item, $i), array_values($columns));
    }

    $note->table($rows, ['header' => true, 'striped' => '#F9FAFB', 'font_size' => 9.5, 'columns' => $widths]);

    // Totals.
    $summary = [
        [$doc->t('total_items'), $quantity($data['totals']['items'])],
        [$doc->t('total_quantity'), $quantity($data['totals']['quantity'])],
    ];

    if (isset($data['packages'])) {
        $summary[] = [$doc->t('packages'), $quantity($data['packages'])];
    }

    $note->table(array_map(fn ($line) => ['', ['text' => $line[0], 'bold' => true], ['text' => $line[1], 'bold' => true, 'align' => 'end']], $summary), [
        'columns' => [55, 30, ['width' => 15, 'align' => 'end']],
        'borders' => false,
        'font_size' => 10,
    ]);

    // Transport.
    if ($transport !== []) {
        $cells = [];

        foreach (['driver', 'phone', 'vehicle'] as $key) {
            if (! empty($transport[$key])) {
                $cells[] = ['lines' => [
                    ['text' => $doc->t($key), 'color' => $muted, 'size' => 8.5],
                    ['text' => (string) $transport[$key], 'bold' => true, 'ltr' => $key === 'phone'],
                ]];
            }
        }

        $note->spacer(2);
        $note->heading($doc->t('transport'), 3);
        $note->table([$cells], ['border_color' => $border, 'font_size' => 9.5]);
    }

    if (! empty($data['notes'])) {
        $note->spacer(2);
        $note->paragraph([['text' => $doc->t('notes').': ', 'bold' => true], $data['notes']], ['size' => 9.5]);
    }

    // Acknowledgement and signature boxes: known roles are translated, anything else is printed as it is.
    $note->spacer(3);
    $note->paragraph($doc->t('acknowledgement'), ['bold' => true, 'size' => 10]);

    if (! empty($data['signatures'])) {
        $note->spacer(4);
        $note->table([array_map(function (string $who) use ($doc, $muted) {
            $label = $doc->t('signatures.'.$who);

            return ['lines' => [
                ['text' => $label === 'signatures.'.$who ? $who : $label, 'bold' => true, 'align' => 'center'],
                ['text' => $doc->t('name').': ....................', 'color' => $muted, 'align' => 'center', 'size' => 9],
                ['text' => ' ', 'size' => 18],
                ['text' => '....................', 'color' => $muted, 'align' => 'center'],
            ]];
        }, array_values($data['signatures']))], ['borders' => false]);
    }
};
