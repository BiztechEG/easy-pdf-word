<?php

use BiztechEG\EasyPdfWord\Support\Html;
use BiztechEG\EasyPdfWord\View\PageLayout;

ob_start();
?>
<h1><?= Html::escape($doc->t('to', ['name' => $to])) ?></h1>
<?php foreach ($lines as $line) { ?>
<p><?= Html::escape($line) ?></p>
<?php } ?>
<p class="muted"><?= $doc->number(1234.5) ?> (<?= Html::escape($count) ?>)</p>
<?php
echo PageLayout::render($doc, ob_get_clean(), styles: '<style>h1 { color: '.$doc->theme('primary').'; }</style>');
