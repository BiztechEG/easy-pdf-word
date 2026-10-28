<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\DocContext;

return fn (DocumentBuilder $h, array $data, DocContext $doc) => $h->paragraph('Layout header: '.$data['company'].' '.$doc->locale);
