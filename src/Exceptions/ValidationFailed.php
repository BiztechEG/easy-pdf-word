<?php

namespace BiztechEG\EasyPdfWord\Exceptions;

use InvalidArgumentException;

/**
 * Template data that does not pass the template's rules, outside Laravel.
 * Laravel apps get Laravel's ValidationException instead, with the same
 * messages, so a request turns it into the usual validation errors.
 *
 *   throw ValidationFailed::withMessages(['items.0.discount' => 'The discount is too large.']);
 */
class ValidationFailed extends InvalidArgumentException
{
    /** @param  array<string, list<string>>  $errors  messages by field */
    public function __construct(private array $errors)
    {
        $first = reset($errors);
        $count = array_sum(array_map('count', $errors)) - 1;

        parent::__construct(is_array($first) && $first !== []
            ? $first[0].($count > 0 ? " (and {$count} more ".($count === 1 ? 'error' : 'errors').')' : '')
            : 'The given data was invalid.');
    }

    /** @param  array<string, string|list<string>>  $messages */
    public static function withMessages(array $messages): self
    {
        return new self(array_map(fn ($message) => array_values((array) $message), $messages));
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
