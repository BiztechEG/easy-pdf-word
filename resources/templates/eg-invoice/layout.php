<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\DocContext;
use Illuminate\Support\Carbon;

/*
| One layout for the PDF and the Word file.
*/

return function (DocumentBuilder $invoice, array $data, DocContext $doc): void {
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $border = $doc->theme('border', '#E5E7EB');
    $document = $data['document'];
    $issuer = $data['issuer'];
    $receiver = $data['receiver'];
    $totals = $data['totals'];
    $currency = $doc->currency($document['currency']);
    $decimals = $doc->decimals($document['currency']);
    $logo = $doc->theme('logo');
    $label = fn (string $key) => ['text' => $doc->t($key).': ', 'color' => $muted];
    $code = fn (?string $value) => ['text' => (string) $value, 'ltr' => true];

    // Title and document identifiers.
    $meta = [
        ['text' => $doc->t('types.'.$document['type']), 'bold' => true, 'size' => 17, 'color' => $primary],
        [$doc->t('internal_id').': ', ['text' => (string) $document['internal_id'], 'ltr' => true]],
        // ETA's dateTimeIssued is UTC ("...Z"); printed in the app's time zone.
        $doc->t('issued_at').': '.Carbon::parse($document['issued_at'])->setTimezone(config('app.timezone'))->format('Y/m/d H:i'),
    ];

    if (! empty($document['purchase_order'])) {
        $meta[] = [$doc->t('purchase_order').': ', ['text' => (string) $document['purchase_order'], 'ltr' => true]];
    }

    $invoice->table([[
        ['lines' => array_values(array_filter([
            $logo ? ['image' => $logo, 'width' => 30] : null,
            ['text' => $issuer['name'], 'bold' => true, 'size' => 14, 'color' => $primary],
            ! empty($issuer['address']) ? ['text' => $issuer['address'], 'color' => $muted] : null,
        ]))],
        ['lines' => $meta],
    ]], ['columns' => [55, 45], 'borders' => false]);

    if (! empty($document['uuid'])) {
        $invoice->paragraph([$label('uuid'), $code($document['uuid']) + ['size' => 9]], ['size' => 9]);
    }

    if (! empty($document['submission_uuid'])) {
        $invoice->paragraph([$label('submission_uuid'), $code($document['submission_uuid']) + ['size' => 9]], ['size' => 9]);
    }

    $invoice->spacer(2);

    // Issuer and receiver.
    $party = function (string $title, array $party, bool $isIssuer) use ($doc, $border, $code, $label) {
        $idLabel = match ($isIssuer ? 'B' : ($party['type'] ?? 'B')) {
            'P' => 'national_id',
            'F' => 'passport',
            default => 'rin',
        };

        $lines = [
            ['text' => $doc->t($title), 'bold' => true, 'size' => 9.5],
            ['text' => $party['name'], 'bold' => true, 'size' => 11],
        ];

        $id = $isIssuer ? ($party['rin'] ?? null) : ($party['id'] ?? null);

        if (! empty($id)) {
            $lines[] = $doc->t($idLabel).': '.$id;
        }

        if ($isIssuer && isset($party['branch_id']) && $party['branch_id'] !== '') {
            $lines[] = $doc->t('branch').': '.$party['branch_id'];
        }

        if ($isIssuer && ! empty($party['activity_code'])) {
            $lines[] = $doc->t('activity').': '.$party['activity_code'];
        }

        if (! empty($party['address'])) {
            $lines[] = $doc->t('address').': '.$party['address'];
        }

        return ['lines' => $lines, 'border' => $border, 'size' => 9.5];
    };

    $invoice->table([[$party('issuer', $issuer, true), '', $party('receiver', $receiver, false)]], ['columns' => [49, 2, 49], 'borders' => false]);
    $invoice->spacer(3);

    // Lines.
    $end = fn (string $text) => ['text' => $text, 'align' => 'end'];
    $rows = [['#', $doc->t('code'), $doc->t('description'), $doc->t('unit'), $end($doc->t('quantity')), $end($doc->t('unit_price')), $end($doc->t('discount')), $end($doc->t('vat')), $end($doc->t('line_total'))]];

    foreach ($data['lines'] as $i => $line) {
        $quantity = (float) $line['quantity'];
        $rows[] = [
            (string) ($i + 1),
            ['lines' => array_values(array_filter([
                ! empty($line['item_type']) ? ['text' => $line['item_type'], 'color' => $muted, 'size' => 7.5] : null,
                $code($line['item_code'] ?? '') + ['size' => 8],
            ]))],
            $line['description'],
            $line['unit'] ?? '',
            $doc->numberText($quantity, floor($quantity) == $quantity ? 0 : 2),
            $doc->numberText($line['unit_price'], $decimals),
            ! empty($line['discount']) ? $doc->numberText($line['discount'], $decimals) : '-',
            $doc->numberText($line['vat'], $decimals),
            $doc->numberText($line['total'], $decimals),
        ];
    }

    $invoice->table($rows, [
        'header' => true,
        'striped' => '#F9FAFB',
        'font_size' => 9,
        'columns' => [4, 18, 22, 8, ['width' => 7, 'align' => 'end'], ['width' => 11, 'align' => 'end'], ['width' => 9, 'align' => 'end'], ['width' => 9, 'align' => 'end'], ['width' => 12, 'align' => 'end']],
    ]);

    // Tax summary and totals side by side is not possible in Word, so the
    // tax summary comes first, then the totals.
    $summary = [];

    foreach ($totals['taxes'] as $type => $amount) {
        $name = $doc->t('tax_types.'.$type);
        $summary[] = ['', $type.' - '.($name === 'tax_types.'.$type ? $type : $name), ($type === 'T4' ? '-' : '').$doc->numberText($amount, $decimals)];
    }

    $sum = [
        ['', $doc->t('sales'), $doc->numberText($totals['sales'], $decimals)],
    ];

    if ($totals['discount'] > 0) {
        $sum[] = ['', $doc->t('total_discount'), '-'.$doc->numberText($totals['discount'], $decimals)];
    }

    $sum[] = ['', $doc->t('net_total'), $doc->numberText($totals['net'], $decimals)];
    $sum = array_merge($sum, $summary);

    if ($totals['extra_discount'] > 0) {
        $sum[] = ['', $doc->t('extra_discount'), '-'.$doc->numberText($totals['extra_discount'], $decimals)];
    }

    $grand = ['bold' => true, 'size' => 11.5, 'color' => '#FFFFFF', 'background' => $primary];
    $sum[] = ['', ['text' => $doc->t('total')] + $grand, ['text' => $doc->numberText($totals['total'], $decimals).' '.$currency] + $grand];

    $invoice->table($sum, ['columns' => [45, 35, ['width' => 20, 'align' => 'end']], 'borders' => false, 'font_size' => 9.5]);

    $words = $doc->inWords($totals['total'], $document['currency']);

    if ($words !== '') {
        $invoice->paragraph([$label('amount_in_words'), $words], ['size' => 9.5]);
    }

    if (! empty($data['qr'])) {
        $invoice->spacer(2);
        $invoice->qr($data['qr'], 28);
        $invoice->paragraph($doc->t('verify'), ['size' => 8, 'color' => $muted]);
    }

    if (! empty($data['notes'])) {
        $invoice->paragraph([$label('notes'), $data['notes']], ['size' => 9]);
    }
};
