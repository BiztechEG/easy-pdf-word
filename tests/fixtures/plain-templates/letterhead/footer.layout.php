<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;

return fn (DocumentBuilder $f) => $f->paragraph('Page {page} of {pages}');
