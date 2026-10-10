<table style="width: 100%; font-size: 8pt; color: #6B7280; border-top: 1px solid #E5E7EB;">
    <tr>
        <td style="text-align: <?= $doc->start() ?>; padding-top: 2mm;"><?= $doc->e($doc->t('title')) ?> <?= $doc->ltr($delivery['number']) ?></td>
        <td style="text-align: <?= $doc->end() ?>; padding-top: 2mm;"><?= $doc->e($doc->t('page')) ?> {page} <?= $doc->e($doc->t('of')) ?> {pages}</td>
    </tr>
</table>
