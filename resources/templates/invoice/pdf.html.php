<?php

use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\Qr;
use BiztechEG\EasyPdfWord\View\PageLayout;

$seller = $seller ?? [];
$primary = $doc->theme('primary', '#0F766E');
$border = $doc->theme('border', '#E5E7EB');
$muted = $doc->theme('muted');
$currency = $invoice['currency'];
$decimals = $doc->decimals($currency);
$currencyLabel = $doc->t('currencies.'.$currency) === 'currencies.'.$currency ? $currency : $doc->t('currencies.'.$currency);
$logo = $doc->image($doc->theme('logo'));
$words = $doc->inWords($totals['total'], $currency);

ob_start();
?>
<style>
    .head td { padding: 0; }
    .brand { font-size: 15pt; font-weight: bold; color: <?= $doc->e($primary) ?>; }
    .doc-title { font-size: 20pt; font-weight: bold; color: <?= $doc->e($primary) ?>; }
    .meta td { padding: 1.5pt 0; font-size: 9.5pt; text-align: <?= $doc->start() ?>; }
    .meta .label { color: <?= $doc->e($muted) ?>; width: 32mm; }
    .party { border: 1px solid <?= $doc->e($border) ?>; padding: 3mm 4mm; }
    .party-title { font-size: 9pt; color: <?= $doc->e($muted) ?>; margin-bottom: 1mm; }
    .party-name { font-size: 11.5pt; font-weight: bold; }
    .items th { background-color: <?= $doc->e($primary) ?>; color: #FFFFFF; font-size: 9.5pt; padding: 2.5mm 2mm; text-align: <?= $doc->start() ?>; }
    .items td { border-bottom: 1px solid <?= $doc->e($border) ?>; padding: 2.5mm 2mm; font-size: 10pt; }
    .items tr.even td { background-color: #F9FAFB; }
    .num, .items th.num { text-align: <?= $doc->end() ?>; white-space: nowrap; }
    .totals td { padding: 1.8mm 2mm; font-size: 10pt; }
    .totals .grand td { background-color: <?= $doc->e($primary) ?>; color: #FFFFFF; font-weight: bold; font-size: 11.5pt; }
    .words { border: 1px dashed <?= $doc->e($primary) ?>; padding: 3mm 4mm; font-size: 10pt; }
</style>
<?php
$styles = ob_get_clean();
ob_start();
?>
<table class="head">
    <tr>
        <td style="width: 55%;">
            <?php if ($logo) { ?>
                <img src="<?= $doc->e($logo) ?>" style="height: 16mm; margin-bottom: 2mm;"><br>
            <?php } ?>
            <div class="brand"><?= $doc->e($seller['name'] ?? '') ?></div>
            <?php if (! empty($seller['address'])) { ?><div class="muted"><?= $doc->e($seller['address']) ?></div><?php } ?>
            <?php if (! empty($seller['phone'])) { ?><div class="muted"><?= $doc->ltr($seller['phone']) ?></div><?php } ?>
        </td>
        <td style="width: 45%;">
            <div class="doc-title"><?= $doc->e($doc->t('title')) ?></div>
            <table class="meta">
                <tr><td class="label"><?= $doc->e($doc->t('number')) ?></td><td><?= $doc->ltr($invoice['number']) ?></td></tr>
                <tr><td class="label"><?= $doc->e($doc->t('date')) ?></td><td><?= $doc->e(Dates::parse($invoice['date'])->format('Y/m/d')) ?></td></tr>
                <?php if ($doc->isRtl() && $doc->hasHijri()) { ?>
                    <tr><td class="label"><?= $doc->e($doc->t('hijri_date')) ?></td><td><?= $doc->e($doc->hijri($invoice['date'])) ?></td></tr>
                <?php } ?>
                <?php if (! empty($invoice['due_date'])) { ?>
                    <tr><td class="label"><?= $doc->e($doc->t('due_date')) ?></td><td><?= $doc->e(Dates::parse($invoice['due_date'])->format('Y/m/d')) ?></td></tr>
                <?php } ?>
            </table>
        </td>
    </tr>
</table>

<table style="margin-top: 7mm;">
    <tr>
        <td class="party" style="width: 49%;">
            <div class="party-title"><?= $doc->e($doc->t('seller')) ?></div>
            <div class="party-name"><?= $doc->e($seller['name'] ?? '') ?></div>
            <?php if (! empty($seller['tax_number'])) { ?><div><?= $doc->e($doc->t('tax_number')) ?>: <?= $doc->ltr($seller['tax_number']) ?></div><?php } ?>
            <?php if (! empty($seller['commercial_register'])) { ?><div><?= $doc->e($doc->t('commercial_register')) ?>: <?= $doc->ltr($seller['commercial_register']) ?></div><?php } ?>
        </td>
        <td style="width: 2%;"></td>
        <td class="party" style="width: 49%;">
            <div class="party-title"><?= $doc->e($doc->t('buyer')) ?></div>
            <div class="party-name"><?= $doc->e($buyer['name']) ?></div>
            <?php if (! empty($buyer['address'])) { ?><div><?= $doc->e($buyer['address']) ?></div><?php } ?>
            <?php if (! empty($buyer['tax_number'])) { ?><div><?= $doc->e($doc->t('tax_number')) ?>: <?= $doc->ltr($buyer['tax_number']) ?></div><?php } ?>
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 7mm;">
    <thead>
        <tr>
            <th style="width: 6%;">#</th>
            <th><?= $doc->e($doc->t('description')) ?></th>
            <th class="num" style="width: 10%;"><?= $doc->e($doc->t('quantity')) ?></th>
            <th class="num" style="width: 15%;"><?= $doc->e($doc->t('unit_price')) ?></th>
            <th class="num" style="width: 13%;"><?= $doc->e($doc->t('discount')) ?></th>
            <th class="num" style="width: 16%;"><?= $doc->e($doc->t('line_total')) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach (array_values($items) as $i => $item) { ?>
            <tr class="<?= $i % 2 === 1 ? 'even' : '' ?>">
                <td><?= $i + 1 ?></td>
                <td><?= $doc->e($item['description']) ?></td>
                <td class="num"><?= $doc->number($item['quantity'], floor($item['quantity']) == $item['quantity'] ? 0 : 2) ?></td>
                <td class="num"><?= $doc->number($item['unit_price'], $decimals) ?></td>
                <td class="num"><?= ! empty($item['discount']) ? $doc->number($item['discount'], $decimals) : '-' ?></td>
                <td class="num"><?= $doc->number($item['total'], $decimals) ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<table style="margin-top: 5mm;">
    <tr>
        <td style="width: 52%; vertical-align: top;">
            <?php if (! empty($qr)) { ?>
                <img src="<?= $doc->e(Qr::dataUri((string) $qr)) ?>" alt="QR" style="width: 30mm; height: 30mm;">
                <?php if (! empty($invoice['eta_uuid'])) { ?>
                    <div class="muted" style="font-size: 8pt;"><?= $doc->e($doc->t('eta_uuid')) ?>: <?= $doc->ltr($invoice['eta_uuid']) ?></div>
                <?php } ?>
            <?php } ?>
        </td>
        <td style="width: 48%;">
            <table class="totals">
                <tr><td><?= $doc->e($doc->t('subtotal')) ?></td><td class="num"><?= $doc->number($totals['subtotal'], $decimals) ?></td></tr>
                <?php if ($totals['discount'] > 0) { ?>
                    <tr><td><?= $doc->e($doc->t('discount')) ?></td><td class="num"><?= $doc->number(-$totals['discount'], $decimals) ?></td></tr>
                <?php } ?>
                <tr><td><?= $doc->e($doc->t('vat', ['rate' => $doc->rate($invoice['tax_rate'])])) ?></td><td class="num"><?= $doc->number($totals['tax'], $decimals) ?></td></tr>
                <tr class="grand"><td><?= $doc->e($doc->t('total')) ?></td><td class="num"><?= $doc->number($totals['total'], $decimals) ?> <?= $doc->e($currencyLabel) ?></td></tr>
            </table>
        </td>
    </tr>
</table>

<?php if ($words !== '') { ?>
    <div class="words" style="margin-top: 5mm;">
        <strong><?= $doc->e($doc->t('amount_in_words')) ?>:</strong> <?= $doc->e($words) ?>
    </div>
<?php } ?>

<?php if (! empty($invoice['notes'])) { ?>
    <div style="margin-top: 6mm;">
        <strong><?= $doc->e($doc->t('notes')) ?></strong>
        <p class="muted"><?= $doc->e($invoice['notes']) ?></p>
    </div>
<?php } ?>
<?php
echo PageLayout::render($doc, ob_get_clean(), $doc->t('title').' '.$invoice['number'], $styles);
