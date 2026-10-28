<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;

return fn (DocumentBuilder $doc, array $data) => $doc->paragraph('Body of the letter');
