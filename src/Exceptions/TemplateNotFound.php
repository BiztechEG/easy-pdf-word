<?php

namespace BiztechEG\EasyPdfWord\Exceptions;

use InvalidArgumentException;

class TemplateNotFound extends InvalidArgumentException
{
    public static function named(string $name, array $paths): self
    {
        return new self("Template [{$name}] was not found in: ".implode(', ', $paths));
    }
}
