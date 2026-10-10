<?php

use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\View\PageLayout;

$company = (array) $doc->theme('company', []);
$primary = $doc->theme('primary', '#0F766E');
$logo = $doc->image($doc->theme('logo'));
$signatureImage = $doc->image($signature ?? null);
$stampImage = $doc->image($stamp ?? null);
$date = Dates::parse($date);

ob_start();
?>
<style>
    .letterhead { border-bottom: 2px solid <?= $doc->e($primary) ?>; padding-bottom: 3mm; }
    .company { font-size: 14pt; font-weight: bold; color: <?= $doc->e($primary) ?>; }
    .ref td { font-size: 9.5pt; padding: 0.8mm 0; text-align: <?= $doc->start() ?>; }
    .ref .label { color: <?= $doc->e($doc->theme('muted')) ?>; width: 26mm; }
    .subject { text-align: center; font-size: 12.5pt; font-weight: bold; margin: 8mm 0 6mm 0; }
    .body p { text-align: justify; line-height: 1.9; margin-bottom: 4mm; font-size: 11pt; }
    .signature { text-align: center; }
</style>
<?php
$styles = ob_get_clean();
ob_start();
?>
<table class="letterhead">
    <tr>
        <td style="width: 60%;">
            <div class="company"><?= $doc->e($company['name'] ?? '') ?></div>
            <?php if (! empty($company['address'])) { ?><div class="muted"><?= $doc->e($company['address']) ?></div><?php } ?>
        </td>
        <td style="width: 40%; text-align: <?= $doc->end() ?>;">
            <?php if ($logo) { ?>
                <img src="<?= $doc->e($logo) ?>" style="height: 18mm;">
            <?php } ?>
        </td>
    </tr>
</table>

<table style="margin-top: 5mm;">
    <tr>
        <td style="width: 55%;"></td>
        <td style="width: 45%;">
            <table class="ref">
                <?php if (! empty($reference)) { ?>
                    <tr><td class="label"><?= $doc->e($doc->t('reference')) ?></td><td><?= $doc->e($reference) ?></td></tr>
                <?php } ?>
                <tr><td class="label"><?= $doc->e($doc->t('date')) ?></td><td><?= $doc->e($date->format('Y/m/d')) ?></td></tr>
                <?php if (($show_hijri ?? true) && $doc->isRtl() && $doc->hasHijri()) { ?>
                    <tr><td class="label"><?= $doc->e($doc->t('hijri')) ?></td><td><?= $doc->e($doc->hijri($date)) ?></td></tr>
                <?php } ?>
            </table>
        </td>
    </tr>
</table>

<div style="margin-top: 6mm;">
    <?php if (! empty($recipient['title'])) { ?><div><?= $doc->e($doc->t('to_title', ['title' => $recipient['title']])) ?></div><?php } ?>
    <div style="font-weight: bold; font-size: 11.5pt;"><?= $doc->e($recipient['name']) ?></div>
    <?php if (! empty($recipient['organization'])) { ?><div><?= $doc->e($recipient['organization']) ?></div><?php } ?>
</div>

<div class="subject"><?= $doc->e($doc->t('subject')) ?>: <?= $doc->e($subject) ?></div>

<div><?= $doc->e($greeting ?? $doc->t('greeting')) ?></div>

<div class="body" style="margin-top: 4mm;">
    <?php foreach ($paragraphs as $paragraph) { ?>
        <p><?= $doc->e($paragraph) ?></p>
    <?php } ?>
</div>

<div style="margin-top: 2mm;"><?= $doc->e($closing ?? $doc->t('closing')) ?></div>

<table style="margin-top: 10mm;">
    <tr>
        <td style="width: 55%;">
            <?php if ($stampImage) { ?>
                <img src="<?= $doc->e($stampImage) ?>" style="height: 30mm;">
            <?php } ?>
        </td>
        <td class="signature" style="width: 45%;">
            <?php if (! empty($sender['title'])) { ?><div><?= $doc->e($sender['title']) ?></div><?php } ?>
            <?php if ($signatureImage) { ?>
                <img src="<?= $doc->e($signatureImage) ?>" style="height: 18mm; margin: 2mm 0;">
            <?php } else { ?>
                <div style="height: 16mm;"></div>
            <?php } ?>
            <div style="font-weight: bold;"><?= $doc->e($sender['name']) ?></div>
        </td>
    </tr>
</table>

<?php if (! empty($cc)) { ?>
    <div style="margin-top: 10mm; font-size: 9.5pt;">
        <div class="muted"><?= $doc->e($doc->t('cc')) ?>:</div>
        <?php foreach ($cc as $copy) { ?>
            <div>- <?= $doc->e($copy) ?></div>
        <?php } ?>
    </div>
<?php } ?>
<?php
echo PageLayout::render($doc, ob_get_clean(), $subject, $styles);
