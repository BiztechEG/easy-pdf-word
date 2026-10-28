<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;

return fn (DocumentBuilder $h, array $data) => $h->paragraph('First page: '.$data['company']);
