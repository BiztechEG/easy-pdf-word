<?php

namespace BiztechEG\EasyPdfWord\Exceptions;

use RuntimeException;

class DriverNotAvailable extends RuntimeException
{
    public static function missingPackage(string $driver, string $package): self
    {
        return new self("The [{$driver}] PDF engine needs the {$package} package. Run: composer require {$package}");
    }
}
