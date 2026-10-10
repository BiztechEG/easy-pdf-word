<?php
    $company = (array) $doc->theme('company', []);
    $contact = array_values(array_filter([
        $company['address'] ?? null,
        ! empty($company['phone']) ? $doc->ltr($company['phone']) : null,
        ! empty($company['email']) ? $doc->ltr($company['email']) : null,
    ]));
?>
<table style="width: 100%; font-size: 8pt; color: #6B7280; border-top: 1px solid #E5E7EB;">
    <tr>
        <td style="text-align: <?= $doc->start() ?>; padding-top: 2mm;">
            <?php foreach ($contact as $i => $part) { ?><?= $doc->e($part) ?><?php if ($i < count($contact) - 1) { ?> | <?php } ?> <?php } ?>
        </td>
        <td style="text-align: <?= $doc->end() ?>; padding-top: 2mm; width: 25%;"><?= $doc->e($doc->t('page')) ?> {page} <?= $doc->e($doc->t('of')) ?> {pages}</td>
    </tr>
</table>
