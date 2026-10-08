<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\DocContext;
use Illuminate\Support\Carbon;

/*
| The Word version of the certificate, built from the same data as
| pdf.blade.php. Word has no page frame here, so the text is centred.
*/

return function (DocumentBuilder $word, array $data, DocContext $doc): void {
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $logo = $doc->theme('logo');
    $type = $data['type'];
    $format = fn ($value) => Carbon::parse($value)->format('Y/m/d');
    $center = fn (array $style = []) => $style + ['align' => 'center', 'space_after' => 2];

    $word->spacer(6);

    if ($logo) {
        $word->image($logo, 25, 'center');
    }

    if (! empty($data['issuer'])) {
        $word->paragraph($data['issuer'], $center(['size' => 14, 'bold' => true]));
    }

    $word->paragraph($doc->t('title.'.$type), $center(['size' => 30, 'bold' => true, 'color' => $primary, 'space_after' => 5]));
    $word->paragraph($doc->t('intro.'.$type), $center(['size' => 13]));
    $word->paragraph($data['recipient'], $center(['size' => 26, 'bold' => true]));
    $word->paragraph($doc->t('statement.'.$type.'.'.$data['gender']), $center(['size' => 13]));
    $word->paragraph($data['course'], $center(['size' => 17, 'bold' => true, 'color' => $primary, 'space_after' => 3]));

    $facts = array_values(array_filter([
        ! empty($data['from']) && ! empty($data['to'])
            ? $doc->t('period', ['from' => $format($data['from']), 'to' => $format($data['to'])])
            : (($day = ($data['from'] ?? null) ?: ($data['to'] ?? null)) ? $doc->t('on', ['date' => $format($day)]) : null),
        ! empty($data['hours_form']) ? $doc->t('hours.'.$data['hours_form'], ['hours' => $doc->rate($data['hours'])]) : null,
        ! empty($data['grade']) ? $doc->t('grade', ['grade' => $data['grade']]) : null,
    ]));

    if ($facts !== []) {
        $word->paragraph(implode($doc->isRtl() ? '، ' : ', ', $facts), $center(['size' => 11, 'color' => $muted]));
    }

    if (! empty($data['details'])) {
        $word->paragraph($data['details'], $center(['size' => 11, 'color' => $muted]));
    }

    // Signatures side by side.
    $signatures = array_values($data['signatures'] ?? []);

    if ($signatures !== []) {
        $word->spacer(12);
        $word->table([array_map(fn (array $signature) => ['lines' => array_values(array_filter([
            ['text' => '....................', 'color' => $muted, 'align' => 'center'],
            ['text' => $signature['name'], 'bold' => true, 'align' => 'center'],
            ! empty($signature['title']) ? ['text' => $signature['title'], 'color' => $muted, 'align' => 'center'] : null,
        ]))], $signatures)], ['borders' => false, 'font_size' => 10.5]);
    }

    // Date, number and the QR code to verify it.
    $word->spacer(4);
    $meta = [$doc->t('date').': '.$format($data['date'])];

    if (! empty($data['number'])) {
        array_push($meta, '   |   '.$doc->t('number').': ', ['text' => (string) $data['number'], 'ltr' => true]);
    }

    $word->paragraph($meta, $center(['size' => 9, 'color' => $muted]));

    if (! empty($data['verify_url'])) {
        $word->qr($data['verify_url'], 20, 'center');
        $word->paragraph($doc->t('verify'), $center(['size' => 7.5, 'color' => $muted]));
    }
};
