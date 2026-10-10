<?php

use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\Qr;
use BiztechEG\EasyPdfWord\View\PageLayout;

$primary = $doc->theme('primary', '#0F766E');
$muted = $doc->theme('muted', '#6B7280');
$logo = $doc->image($doc->theme('logo'));
$format = fn ($value) => Dates::parse($value)->format('Y/m/d');

// "خلال الفترة من ... إلى ...، بعدد 40 ساعة تدريبية، بتقدير امتياز"
$facts = array_values(array_filter([
    ! empty($from) && ! empty($to) ? $doc->t('period', ['from' => $format($from), 'to' => $format($to)]) : (($day = ($from ?? null) ?: ($to ?? null)) ? $doc->t('on', ['date' => $format($day)]) : null),
    ! empty($hours_form) ? $doc->t('hours.'.$hours_form, ['hours' => $doc->rate($hours)]) : null,
    ! empty($grade) ? $doc->t('grade', ['grade' => $grade]) : null,
]));
$signatures = array_values($signatures ?? []);

ob_start();
?>
<style>
    .frame { border: 2.2mm double <?= $doc->e($primary) ?>; padding: 2mm; }
    .inner { border: 0.3mm solid <?= $doc->e($primary) ?>; height: 170mm; padding: 7mm 12mm 0 12mm; text-align: center; }
    .corner td { font-size: 8.5pt; color: <?= $doc->e($muted) ?>; vertical-align: top; }
    .issuer { font-size: 14pt; font-weight: bold; }
    .title { font-size: 34pt; font-weight: bold; color: <?= $doc->e($primary) ?>; margin: 6mm 0 7mm 0; line-height: 1.2; }
    .intro, .statement { font-size: 14pt; }
    .recipient { font-size: 30pt; font-weight: bold; margin: 3mm 0; line-height: 1.3; }
    .rule { width: 40%; margin: 0 auto 4mm auto; border-top: 0.4mm solid <?= $doc->e($primary) ?>; height: 0; }
    .course { font-size: 19pt; font-weight: bold; color: <?= $doc->e($primary) ?>; margin: 3mm 0 4mm 0; line-height: 1.4; }
    .details { font-size: 11.5pt; color: <?= $doc->e($muted) ?>; }
    .signatures td { text-align: center; font-size: 10.5pt; }
    .signatures .line { border-bottom: 0.3mm solid <?= $doc->e($muted) ?>; height: 14mm; }
</style>
<?php
$styles = ob_get_clean();
ob_start();
?>
<div class="frame">
    <div class="inner">
        <?php // Date and number in one corner, the logo and issuer in the middle, the QR code in the other corner. ?>
        <table class="corner">
            <tr>
                <td style="width: 25%; text-align: <?= $doc->start() ?>;">
                    <div><?= $doc->e($doc->t('date')) ?>: <?= $doc->e($format($date)) ?></div>
                    <?php if (! empty($number)) { ?>
                        <div><?= $doc->e($doc->t('number')) ?>: <?= $doc->ltr($number) ?></div>
                    <?php } ?>
                </td>
                <td style="width: 50%; text-align: center;">
                    <?php if ($logo) { ?>
                        <img src="<?= $doc->e($logo) ?>" style="height: 16mm;">
                    <?php } ?>
                    <?php if (! empty($issuer)) { ?>
                        <div class="issuer"><?= $doc->e($issuer) ?></div>
                    <?php } ?>
                </td>
                <td style="width: 25%; text-align: <?= $doc->end() ?>;">
                    <?php if (! empty($verify_url)) { ?>
                        <img src="<?= $doc->e(Qr::dataUri($verify_url)) ?>" style="width: 20mm;">
                        <div style="font-size: 7pt;"><?= $doc->e($doc->t('verify')) ?></div>
                    <?php } ?>
                </td>
            </tr>
        </table>

        <div class="title"><?= $doc->e($doc->t('title.'.$type)) ?></div>
        <div class="intro"><?= $doc->e($doc->t('intro.'.$type)) ?></div>
        <div class="recipient"><?= $doc->e($recipient) ?></div>
        <div class="rule"></div>
        <div class="statement"><?= $doc->e($doc->t('statement.'.$type.'.'.$gender)) ?></div>
        <div class="course"><?= $doc->e($course) ?></div>

        <?php if ($facts !== []) { ?>
            <div class="details"><?= $doc->e(implode($doc->isRtl() ? '، ' : ', ', $facts)) ?></div>
        <?php } ?>
        <?php if (! empty($details)) { ?>
            <div class="details"><?= $doc->e($details) ?></div>
        <?php } ?>

        <?php // Signatures: a line to sign on, then the name and title, with gaps between them. ?>
        <?php if ($signatures !== []) { ?>
            <?php
            $width = count($signatures) === 3 ? 26 : 30;
            $gap = round((100 - count($signatures) * $width) / (count($signatures) + 1), 2);
            ?>
            <table class="signatures" style="margin-top: 12mm;">
                <tr>
                    <?php foreach ($signatures as $signature) { ?>
                        <td style="width: <?= $doc->e($gap) ?>%;"></td>
                        <td class="line" style="width: <?= $doc->e($width) ?>%;"></td>
                    <?php } ?>
                    <td style="width: <?= $doc->e($gap) ?>%;"></td>
                </tr>
                <tr>
                    <?php foreach ($signatures as $signature) { ?>
                        <td></td>
                        <td style="padding-top: 1.5mm;">
                            <div style="font-weight: bold;"><?= $doc->e($signature['name']) ?></div>
                            <?php if (! empty($signature['title'])) { ?>
                                <div class="muted"><?= $doc->e($signature['title']) ?></div>
                            <?php } ?>
                        </td>
                    <?php } ?>
                    <td></td>
                </tr>
            </table>
        <?php } ?>
    </div>
</div>
<?php
echo PageLayout::render($doc, ob_get_clean(), $doc->t('title.'.$type), $styles);
