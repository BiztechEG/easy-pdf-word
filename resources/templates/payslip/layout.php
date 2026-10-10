<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\DocContext;

/*
| One layout for the PDF and the Word file.
*/

return function (DocumentBuilder $slip, array $data, DocContext $doc): void {
    $company = (array) $doc->theme('company', []);
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $border = $doc->theme('border', '#E5E7EB');
    $employee = $data['employee'];
    $totals = $data['totals'];
    $currency = $doc->currency($data['currency']);
    $decimals = $doc->decimals($data['currency']);
    $date = fn ($value) => Dates::parse($value)->format('Y/m/d');
    $month = $doc->monthName($data['period'].'-01').' '.substr($data['period'], 0, 4);
    $logo = $doc->theme('logo');

    // Company, title and month.
    $details = [
        ['text' => $doc->t('title'), 'bold' => true, 'size' => 20, 'color' => $primary],
        ['text' => $doc->t('period', ['month' => $month]), 'bold' => true, 'size' => 12],
    ];

    if (! empty($data['number'])) {
        $details[] = [$doc->t('number').': ', ['text' => (string) $data['number'], 'ltr' => true]];
    }

    $slip->table([[
        ['lines' => array_values(array_filter([
            $logo ? ['image' => $logo, 'width' => 30] : null,
            ['text' => $company['name'] ?? '', 'bold' => true, 'size' => 14, 'color' => $primary],
            ! empty($company['address']) ? ['text' => $company['address'], 'color' => $muted] : null,
        ]))],
        ['lines' => $details],
    ]], ['columns' => [55, 45], 'borders' => false]);

    $slip->spacer(3);

    // Employee details, two to a row. Codes and numbers stay left to right.
    $fields = [];

    foreach (['name', 'code', 'job_title', 'department', 'national_id', 'hire_date'] as $key) {
        if (empty($employee[$key])) {
            continue;
        }

        $value = match ($key) {
            'hire_date' => $date($employee[$key]),
            'code', 'national_id' => ['text' => (string) $employee[$key], 'ltr' => true],
            default => $employee[$key],
        };

        $fields[] = [['text' => $doc->t('employee.'.$key), 'color' => $muted, 'background' => '#F9FAFB'], is_array($value) ? $value + ['bold' => $key === 'code'] : ['text' => $value, 'bold' => $key === 'name']];
    }

    if (count($fields) % 2 === 1) {
        $fields[] = ['', ''];
    }

    $rows = array_map(fn (array $pair) => array_merge(...$pair), array_chunk($fields, 2));

    // The bank account (often a long IBAN) gets a row of its own.
    if (! empty($employee['bank']) || ! empty($employee['bank_account'])) {
        $rows[] = [
            ['text' => $doc->t('employee.bank_account'), 'color' => $muted, 'background' => '#F9FAFB'],
            ['lines' => [array_values(array_filter([
                ! empty($employee['bank']) ? $employee['bank'].(! empty($employee['bank_account']) ? '  ' : '') : null,
                ! empty($employee['bank_account']) ? ['text' => (string) $employee['bank_account'], 'ltr' => true] : null,
            ]))], 'colspan' => 3],
        ];
    }

    $slip->table($rows, [
        'columns' => [18, 32, 18, 32],
        'border_color' => $border,
        'font_size' => 9.5,
    ]);

    $slip->spacer(2);

    // Earnings and deductions side by side.
    $earnings = array_values($data['earnings']);
    $deductions = array_values($data['deductions'] ?? []);
    $amount = fn (array $line) => $doc->numberText($line['amount'], $decimals);
    $rows = [[$doc->t('earnings'), $doc->t('amount'), $doc->t('deductions'), $doc->t('amount')]];

    for ($i = 0; $i < max(count($earnings), count($deductions)); $i++) {
        $rows[] = [
            $earnings[$i]['name'] ?? '',
            isset($earnings[$i]) ? $amount($earnings[$i]) : '',
            $deductions[$i]['name'] ?? '',
            isset($deductions[$i]) ? $amount($deductions[$i]) : '',
        ];
    }

    $rows[] = [
        $doc->t('total_earnings'), $doc->numberText($totals['earnings'], $decimals),
        $doc->t('total_deductions'), $doc->numberText($totals['deductions'], $decimals),
    ];

    $slip->table($rows, [
        'header' => true,
        'footer' => true,
        'striped' => '#F9FAFB',
        'font_size' => 10,
        'columns' => [32, ['width' => 18, 'align' => 'end'], 32, ['width' => 18, 'align' => 'end']],
    ]);

    // Net pay.
    $grand = ['bold' => true, 'size' => 12, 'color' => '#FFFFFF', 'background' => $primary];
    $slip->table([[
        '',
        ['text' => $doc->t('net')] + $grand,
        ['text' => $doc->numberText($totals['net'], $decimals).' '.$currency, 'align' => 'end'] + $grand,
    ]], ['columns' => [52, 28, 20], 'borders' => false]);

    $words = $totals['net'] >= 0 ? $doc->inWords($totals['net'], $data['currency']) : '';

    if ($words !== '') {
        $slip->paragraph($words, ['color' => $muted, 'size' => 9.5]);
    }

    // Attendance, one box per figure given.
    $attendance = array_filter((array) ($data['attendance'] ?? []), fn ($value) => $value !== null && $value !== '');

    if ($attendance !== []) {
        $slip->spacer(2);
        $slip->table([array_map(fn (string $key) => [
            'lines' => [
                ['text' => $doc->t('attendance.'.$key), 'color' => $muted, 'size' => 8.5, 'align' => 'center'],
                ['text' => $doc->rate($attendance[$key]), 'bold' => true, 'size' => 13, 'align' => 'center'],
            ],
            'border' => $border,
        ], array_keys($attendance))], ['borders' => false]);
    }

    // How and when it was paid.
    $payment = (array) ($data['payment'] ?? []);
    $paid = [];

    if (! empty($payment['method'])) {
        array_push($paid, ['text' => $doc->t('payment_method').': ', 'bold' => true], $doc->t('methods.'.$payment['method']));
    }

    if (! empty($payment['date'])) {
        if ($paid !== []) {
            $paid[] = ['text' => '   |   ', 'color' => $muted];
        }

        array_push($paid, ['text' => $doc->t('payment_date').': ', 'bold' => true], $date($payment['date']));
    }

    if ($paid !== []) {
        $slip->spacer(1);
        $slip->paragraph($paid, ['size' => 9.5]);
    }

    if (! empty($data['notes'])) {
        $slip->paragraph([['text' => $doc->t('notes').': ', 'bold' => true], $data['notes']], ['size' => 9.5]);
    }

    // Signature boxes: known roles are translated, anything else is printed as it is.
    if (! empty($data['signatures'])) {
        $slip->spacer(6);
        $slip->table([array_map(function (string $who) use ($doc, $muted) {
            $label = $doc->t('signatures.'.$who);

            return ['lines' => [
                ['text' => $label === 'signatures.'.$who ? $who : $label, 'bold' => true, 'align' => 'center'],
                ['text' => ' ', 'size' => 22],
                ['text' => '....................', 'color' => $muted, 'align' => 'center'],
            ]];
        }, array_values($data['signatures']))], ['borders' => false]);
    }
};
