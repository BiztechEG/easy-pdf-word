<?php

use BiztechEG\EasyPdfWord\Support\Data;
use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\View\PageLayout;

$primary = $doc->theme('primary', '#0F766E');
$border = $doc->theme('border', '#E5E7EB');
$company = (array) $doc->theme('company', []);
$align = fn ($column) => match ($column['align']) {
    'end' => $doc->end(),
    'center' => 'center',
    default => $doc->start(),
};
$format = function ($column, $value) use ($doc) {
    return match ($column['format']) {
        'number', 'money' => $value === null || $value === '' ? '' : $doc->number($value, $column['decimals']),
        'date' => $value ? Dates::parse($value)->format('Y/m/d') : '',
        default => $doc->text($value),
    };
};
$last = count($summary ?? []) - 1;

ob_start();
?>
<style>
    .title { font-size: 17pt; font-weight: bold; color: <?= $doc->e($primary) ?>; }
    .summary td.card { border: 1px solid <?= $doc->e($border) ?>; padding: 3mm; }
    .summary .value { font-size: 12.5pt; font-weight: bold; color: <?= $doc->e($primary) ?>; }
    .data th { background-color: <?= $doc->e($primary) ?>; color: #FFFFFF; padding: 2.2mm 2mm; font-size: 9.5pt; }
    .data td { padding: 2mm; border-bottom: 1px solid <?= $doc->e($border) ?>; font-size: 9.5pt; }
    .data tr.even td { background-color: #F9FAFB; }
    .data tr.total td { font-weight: bold; border-top: 2px solid <?= $doc->e($primary) ?>; background-color: #F3F4F6; }
</style>
<?php
$styles = ob_get_clean();
ob_start();
?>
<table>
    <tr>
        <td style="width: 65%;">
            <div class="title"><?= $doc->e($title) ?></div>
            <?php if (! empty($subtitle)) { ?><div class="muted"><?= $doc->e($subtitle) ?></div><?php } ?>
        </td>
        <td style="width: 35%; text-align: <?= $doc->end() ?>;" class="muted">
            <div><?= $doc->e($company['name'] ?? '') ?></div>
            <div style="font-size: 9pt;"><?= $doc->e($doc->t('generated_at')) ?>: <?= $doc->e(Dates::parse($generated_at)->format('Y/m/d H:i')) ?></div>
        </td>
    </tr>
</table>

<?php if (! empty($summary)) { ?>
    <table class="summary" style="margin-top: 6mm;">
        <tr>
            <?php foreach (array_keys($summary) as $i => $label) { ?>
                <td class="card">
                    <div class="muted" style="font-size: 9pt;"><?= $doc->e($label) ?></div>
                    <div class="value"><?= $doc->e($summary[$label]) ?></div>
                </td>
                <?php if ($i < $last) { ?><td style="width: 3mm;"></td><?php } ?>
            <?php } ?>
        </tr>
    </table>
<?php } ?>

<table class="data" style="margin-top: 6mm;">
    <thead>
        <tr>
            <th style="width: 7mm; text-align: <?= $doc->start() ?>;">#</th>
            <?php foreach ($columns as $column) { ?>
                <th style="text-align: <?= $doc->e($align($column)) ?>;"><?= $doc->e($column['label']) ?></th>
            <?php } ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach (array_values($rows) as $i => $row) { ?>
            <tr class="<?= $i % 2 === 1 ? 'even' : '' ?>">
                <td><?= $i + 1 ?></td>
                <?php foreach ($columns as $column) { ?>
                    <td style="text-align: <?= $doc->e($align($column)) ?>;"><?= $doc->e($format($column, Data::get($row, $column['key']))) ?></td>
                <?php } ?>
            </tr>
        <?php } ?>
        <?php if ($rows === []) { ?>
            <tr><td colspan="<?= count($columns) + 1 ?>" class="text-center muted"><?= $doc->e($doc->t('empty')) ?></td></tr>
        <?php } ?>
        <?php if (! empty($totals)) { ?>
            <tr class="total">
                <td></td>
                <?php foreach (array_values($columns) as $i => $column) { ?>
                    <td style="text-align: <?= $doc->e($align($column)) ?>;">
                        <?php if (array_key_exists($column['key'], $totals)) { ?>
                            <?= $doc->e($format($column, $totals[$column['key']])) ?>
                        <?php } elseif ($i === 0) { ?>
                            <?= $doc->e($doc->t('total')) ?>
                        <?php } ?>
                    </td>
                <?php } ?>
            </tr>
        <?php } ?>
    </tbody>
</table>
<?php
echo PageLayout::render($doc, ob_get_clean(), $title, $styles);
