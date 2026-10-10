<?php
    // The month by name ("سبتمبر 2026"), so it does not run into the employee code in Arabic.
    $month = $doc->monthName($period.'-01').' '.substr($period, 0, 4);
?>
<table style="width: 100%; font-size: 8pt; color: #6B7280; border-top: 1px solid #E5E7EB;">
    <tr>
        <td style="text-align: <?= $doc->start() ?>; padding-top: 2mm;"><?= $doc->e($doc->t('confidential')) ?> - <?= $doc->e($doc->t('title')) ?> <?= $doc->e($month) ?><?php if (! empty($employee['code'])) { ?> - <?= $doc->ltr($employee['code']) ?><?php } ?></td>
        <td style="text-align: <?= $doc->end() ?>; padding-top: 2mm;"><?= $doc->e($doc->t('page')) ?> {page} <?= $doc->e($doc->t('of')) ?> {pages}</td>
    </tr>
</table>
