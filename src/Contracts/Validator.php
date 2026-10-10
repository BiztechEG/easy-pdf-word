<?php

namespace BiztechEG\EasyPdfWord\Contracts;

/**
 * Checks template data against the rules in a template's "fields".
 */
interface Validator
{
    /**
     * @param  array<string, string|array>  $rules  Laravel-style rules by dotted path: 'items.*.quantity' => ['required', 'numeric']
     *
     * @throws \Throwable when the data does not pass: ValidationFailed in plain PHP, ValidationException in Laravel
     */
    public function validate(array $data, array $rules): void;
}
