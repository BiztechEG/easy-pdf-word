<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\DocContext;

/*
| One layout for the PDF and the Word file.
*/

return function (DocumentBuilder $contract, array $data, DocContext $doc): void {
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $border = $doc->theme('border', '#E5E7EB');
    $info = $data['contract'];
    $parties = $data['parties'];
    $date = Dates::parse($info['date']);
    $logo = $doc->theme('logo');

    // "الأول", "الثاني" ... or the number past the listed ordinals.
    $ordinal = function (int $n) use ($doc): string {
        $word = $doc->t('ordinals.'.$n);

        return $word === 'ordinals.'.$n ? (string) $n : $word;
    };
    $label = fn (string $key, int $n) => $doc->t($key, ['ordinal' => $ordinal($n), 'Ordinal' => ucfirst($ordinal($n)), 'number' => $n]);
    $paragraph = ['align' => 'justify', 'size' => 11, 'line_height' => 1.5, 'space_after' => 2];

    // Title, number and the opening sentence.
    if ($logo) {
        $contract->image($logo, 30, 'center');
    }

    $contract->paragraph($info['title'], ['bold' => true, 'size' => 20, 'color' => $primary, 'align' => 'center', 'space_after' => 1]);

    if (! empty($info['number'])) {
        $contract->paragraph([$doc->t('number').': ', ['text' => (string) $info['number'], 'ltr' => true]], ['align' => 'center', 'color' => $muted, 'size' => 10]);
    }

    $contract->line($primary);
    $contract->paragraph($doc->t(empty($info['place']) ? 'intro' : 'intro_place', [
        'day' => $doc->dayName($date),
        'date' => $date->format('Y/m/d'),
        'place' => $info['place'] ?? '',
    ]), $paragraph + ['bold' => true]);

    // The parties.
    $rows = [];

    foreach ($parties as $i => $party) {
        $details = [];

        if (! empty($party['id'])) {
            $id = ['text' => (string) $party['id'], 'ltr' => true];
            $details[] = empty($party['id_label']) ? [$id] : [$party['id_label'].' ', $id];
        }

        if (! empty($party['address'])) {
            $details[] = $doc->t('address').': '.$party['address'];
        }

        if (! empty($party['represented_by'])) {
            $details[] = $doc->t('represented_by').': '.$party['represented_by'].(! empty($party['capacity']) ? ' ('.$party['capacity'].')' : '');
        }

        $rows[] = [
            ['lines' => array_values(array_filter([
                ['text' => $label('party', $i + 1), 'bold' => true, 'color' => $primary],
                ! empty($party['alias']) ? ['text' => '('.$party['alias'].')', 'color' => $muted, 'size' => 9.5] : null,
            ])), 'background' => '#F9FAFB'],
            ['lines' => array_merge([['text' => $party['name'], 'bold' => true, 'size' => 11.5]], $details)],
        ];
    }

    $contract->table($rows, ['columns' => [22, 78], 'border_color' => $border, 'font_size' => 10]);

    // Preamble.
    if (! empty($data['preamble_paragraphs'])) {
        $contract->heading($doc->t('preamble'), 3, ['color' => $primary]);

        foreach ($data['preamble_paragraphs'] as $text) {
            $contract->paragraph($text, $paragraph);
        }
    }

    $contract->paragraph($doc->t(count($parties) > 2 ? 'agreed_many' : 'agreed'), $paragraph + ['bold' => true]);

    // Clauses: "البند الأول: موضوع العقد", then its paragraphs.
    foreach (array_values($data['clauses']) as $i => $clause) {
        $title = $label('clause', $i + 1).(! empty($clause['title']) ? ': '.$clause['title'] : '');
        $contract->heading($title, 3, ['color' => $primary]);

        foreach ($clause['paragraphs'] as $text) {
            $contract->paragraph($text, $paragraph);
        }
    }

    // Copies.
    $copies = (int) ($data['copies'] ?? 2);
    $copiesText = $doc->t('copies.'.$copies);
    $copiesText = $copiesText === 'copies.'.$copies ? $doc->t('copies_count', ['count' => $copies]) : $copiesText;

    $contract->spacer(2);
    $contract->paragraph($data['closing'] ?? $doc->t('closing', ['copies' => $copiesText]), $paragraph + ['bold' => true]);

    // Signatures: one box per party, three to a row.
    $sign = fn (string $heading, ?string $alias, ?string $name, ?string $by) => ['lines' => array_values(array_filter([
        ['text' => $heading, 'bold' => true, 'color' => $primary, 'align' => 'center'],
        $alias ? ['text' => '('.$alias.')', 'color' => $muted, 'size' => 9, 'align' => 'center'] : null,
        $name ? ['text' => $name, 'bold' => true, 'align' => 'center'] : null,
        $by ? ['text' => $by, 'size' => 9.5, 'align' => 'center'] : null,
        ['text' => ' ', 'size' => 14],
        ['text' => $doc->t('signature').': ....................', 'color' => $muted, 'align' => 'center'],
    ]))];

    $contract->spacer(6);

    foreach (array_chunk($parties, 3, true) as $chunk) {
        $contract->table([array_map(fn (array $party, int $i) => $sign(
            $label('party', $i + 1),
            $party['alias'] ?? null,
            $party['name'],
            $party['represented_by'] ?? null,
        ), $chunk, array_keys($chunk))], ['borders' => false, 'font_size' => 10]);
    }

    // Witnesses.
    if (! empty($data['witnesses'])) {
        $contract->spacer(4);
        $contract->heading($doc->t('witnesses'), 3, ['color' => $primary]);

        foreach (array_chunk(array_values($data['witnesses']), 3, true) as $chunk) {
            $contract->table([array_map(fn (string $name, int $i) => ['lines' => [
                ['text' => $label('witness', $i + 1), 'bold' => true, 'align' => 'center'],
                ['text' => $doc->t('name').': '.$name, 'align' => 'center'],
                ['text' => ' ', 'size' => 10],
                ['text' => $doc->t('signature').': ....................', 'color' => $muted, 'align' => 'center'],
            ]], $chunk, array_keys($chunk))], ['borders' => false, 'font_size' => 10]);
        }
    }
};
