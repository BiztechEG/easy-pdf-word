<?php

namespace BiztechEG\EasyPdfWord\Exceptions;

use LogicException;

class WordNotSupported extends LogicException
{
    public static function forTemplate(string $name): self
    {
        return new self("Template [{$name}] has no Word layout. Add word.php or word.docx to its folder.");
    }

    public static function forSource(): self
    {
        return new self('Word files are made from a template with word.php or word.docx, or from Doc::make(). Blade views and HTML only make PDFs.');
    }
}
