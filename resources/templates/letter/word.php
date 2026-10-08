<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\DocContext;
use Illuminate\Support\Carbon;

/*
| The Word version of the letter, built from the same data as pdf.blade.php.
*/

return function (DocumentBuilder $word, array $data, DocContext $doc): void {
    $company = (array) $doc->theme('company', []);
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $logo = $doc->theme('logo');
    $date = Carbon::parse($data['date']);

    // Letterhead.
    $word->table([[
        ['lines' => array_filter([
            ['text' => $company['name'] ?? '', 'bold' => true, 'size' => 14, 'color' => $primary],
            ! empty($company['address']) ? ['text' => $company['address'], 'color' => $muted] : null,
        ])],
        $logo ? ['image' => $logo, 'width' => 30, 'align' => 'end'] : '',
    ]], ['columns' => [60, 40], 'borders' => false]);
    $word->line($primary);

    // Reference and dates.
    $reference = [];

    if (! empty($data['reference'])) {
        $reference[] = ['', ['text' => $doc->t('reference'), 'color' => $muted], $data['reference']];
    }

    $reference[] = ['', ['text' => $doc->t('date'), 'color' => $muted], $date->format('Y/m/d')];

    if (($data['show_hijri'] ?? true) && $doc->isRtl() && $doc->hasHijri()) {
        $reference[] = ['', ['text' => $doc->t('hijri'), 'color' => $muted], $doc->hijri($date)];
    }

    $word->table($reference, ['columns' => [55, 15, 30], 'borders' => false, 'font_size' => 9.5]);
    $word->spacer(3);

    // Recipient.
    $recipient = $data['recipient'];

    if (! empty($recipient['title'])) {
        $word->paragraph($doc->t('to_title', ['title' => $recipient['title']]), ['space_after' => 0]);
    }

    $word->paragraph($recipient['name'], ['bold' => true, 'size' => 11.5, 'space_after' => 0]);

    if (! empty($recipient['organization'])) {
        $word->paragraph($recipient['organization']);
    }

    $word->spacer(4);
    $word->paragraph($doc->t('subject').': '.$data['subject'], ['bold' => true, 'size' => 12.5, 'align' => 'center', 'space_after' => 6]);
    $word->paragraph($data['greeting'] ?? $doc->t('greeting'), ['space_after' => 4]);

    foreach ($data['paragraphs'] as $paragraph) {
        $word->paragraph($paragraph, ['align' => 'justify', 'line_height' => 1.5, 'space_after' => 4]);
    }

    $word->paragraph($data['closing'] ?? $doc->t('closing'));
    $word->spacer(6);

    // Stamp and signature.
    $sender = $data['sender'];
    $signature = array_values(array_filter([
        ! empty($sender['title']) ? ['text' => $sender['title'], 'align' => 'center'] : null,
        ! empty($data['signature']) ? ['image' => $data['signature'], 'width' => 35, 'align' => 'center'] : ['text' => ' ', 'size' => 28],
        ['text' => $sender['name'], 'bold' => true, 'align' => 'center'],
    ]));

    $word->table([[
        ! empty($data['stamp']) ? ['image' => $data['stamp'], 'width' => 30] : '',
        ['lines' => $signature],
    ]], ['columns' => [55, 45], 'borders' => false]);

    if (! empty($data['cc'])) {
        $word->spacer(8);
        $word->paragraph($doc->t('cc').':', ['color' => $muted, 'size' => 9.5, 'space_after' => 0]);

        foreach ($data['cc'] as $copy) {
            $word->paragraph('- '.$copy, ['size' => 9.5, 'space_after' => 0]);
        }
    }
};
