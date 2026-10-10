<?php

namespace BiztechEG\EasyPdfWord\Validation;

use BiztechEG\EasyPdfWord\Contracts\Validator;
use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;
use DateTime;
use DateTimeInterface;
use LogicException;

/**
 * Template validation without Laravel. It understands the Laravel rules
 * that templates use, with Laravel's meaning, so one template.php works in
 * both: required, required_if, present, nullable, string, numeric, integer,
 * boolean, array, date, date_format, min, max, size, in, gt, after_or_equal
 * and regex. Any other rule throws a LogicException naming it.
 */
class RuleValidator implements Validator
{
    /** Rules that run even when the field is missing or empty. */
    private const IMPLICIT = ['required', 'required_if', 'present'];

    /** Rules that make min, max, size and gt compare the value, not its length. */
    private const NUMERIC = ['numeric', 'integer'];

    public function validate(array $data, array $rules): void
    {
        $errors = [];

        foreach ($rules as $pattern => $list) {
            $list = $this->parse($list, (string) $pattern);
            $names = array_column($list, 0);

            foreach ($this->expand($data, explode('.', (string) $pattern)) as $attribute) {
                [$exists, $value] = $this->lookup($data, $attribute);

                foreach ($list as [$rule, $parameters]) {
                    if ($rule === 'nullable' || ! $this->runs($rule, $exists, $value, $names)) {
                        continue;
                    }

                    if (! $this->passes($rule, $parameters, $value, $exists, $data, $attribute, $names)) {
                        $label = str_contains((string) $pattern, '*') ? $attribute : self::label($attribute);
                        $errors[$attribute][] = $this->message($rule, $parameters, $label, $names);

                        // Laravel stops checking a field once "required" and the like fail.
                        if (in_array($rule, self::IMPLICIT, true)) {
                            break;
                        }
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }
    }

    /** @return list<array{0: string, 1: list<string>}> rule name and parameters */
    private function parse(string|array $rules, string $pattern): array
    {
        $parsed = [];

        foreach (is_string($rules) ? explode('|', $rules) : $rules as $rule) {
            if (! is_string($rule)) {
                throw new LogicException("The rule for [{$pattern}] is an object or closure; that needs Laravel's validator.");
            }

            [$name, $parameters] = array_pad(explode(':', $rule, 2), 2, null);
            $name = strtolower(trim($name));
            // A regex can hold commas; Laravel keeps it whole too.
            $parameters = $parameters === null ? [] : ($name === 'regex' ? [$parameters] : str_getcsv($parameters, ',', '"', ''));
            $parsed[] = [$name, $parameters];
        }

        return $parsed;
    }

    /**
     * "items.*.quantity" as items.0.quantity, items.1.quantity, ... for the
     * items that exist. A field missing at the end is still listed, so
     * "required" can report it.
     *
     * @param  list<string>  $segments
     * @return list<string>
     */
    private function expand(array $data, array $segments): array
    {
        $paths = [[]];

        foreach ($segments as $segment) {
            $next = [];

            foreach ($paths as $path) {
                if ($segment !== '*') {
                    $next[] = [...$path, $segment];

                    continue;
                }

                [, $list] = $this->lookup($data, implode('.', $path));

                foreach (is_array($list) ? array_keys($list) : [] as $key) {
                    $next[] = [...$path, (string) $key];
                }
            }

            $paths = $next;
        }

        return array_map(fn (array $path) => implode('.', $path), $paths);
    }

    /** @return array{0: bool, 1: mixed} whether the dotted path exists, and its value */
    private function lookup(array $data, string $attribute): array
    {
        if ($attribute === '') {
            return [true, $data];
        }

        $value = $data;

        foreach (explode('.', $attribute) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return [false, null];
            }

            $value = $value[$segment];
        }

        return [true, $value];
    }

    /** As in Laravel: other rules skip missing fields, empty strings, and null when "nullable". */
    private function runs(string $rule, bool $exists, mixed $value, array $names): bool
    {
        if (in_array($rule, self::IMPLICIT, true)) {
            return true;
        }

        if (! $exists || (is_string($value) && trim($value) === '')) {
            return false;
        }

        return ! ($value === null && in_array('nullable', $names, true));
    }

    private function passes(string $rule, array $parameters, mixed $value, bool $exists, array $data, string $attribute, array $names): bool
    {
        return match ($rule) {
            'required' => $this->filled($value, $exists),
            'present' => $exists,
            'required_if' => ! $this->otherMatches($data, $attribute, $parameters) || $this->filled($value, $exists),
            'string' => is_string($value),
            'numeric' => is_numeric($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1'], true),
            'array' => is_array($value),
            'date' => $this->isDate($value),
            'date_format' => $this->matchesFormat($value, $parameters[0] ?? ''),
            'min' => $this->size($value, $names) >= (float) ($parameters[0] ?? 0),
            'max' => $this->size($value, $names) <= (float) ($parameters[0] ?? 0),
            'size' => $this->size($value, $names) == (float) ($parameters[0] ?? 0),
            'in' => ! is_array($value) && in_array((string) (is_bool($value) ? (int) $value : $value), $parameters, true),
            'gt' => $this->greaterThan($value, $parameters[0] ?? '', $data, $attribute, $names),
            'after_or_equal' => $this->afterOrEqual($value, $parameters[0] ?? '', $data, $attribute),
            'regex' => (is_string($value) || is_numeric($value)) && @preg_match($parameters[0] ?? '', (string) $value) > 0,
            default => throw new LogicException("The rule [{$rule}] on [{$attribute}] needs Laravel's validator."),
        };
    }

    private function filled(mixed $value, bool $exists): bool
    {
        return match (true) {
            ! $exists, $value === null => false,
            is_string($value) => trim($value) !== '',
            is_countable($value) => count($value) > 0,
            default => true,
        };
    }

    /** required_if:method,cheque,transfer: whether "method" is one of the values. */
    private function otherMatches(array $data, string $attribute, array $parameters): bool
    {
        [$exists, $other] = $this->lookup($data, $this->sibling($parameters[0] ?? '', $attribute));
        $values = array_slice($parameters, 1);

        if (! $exists || is_array($other)) {
            return in_array('null', $values, true) && $other === null;
        }

        $other = match (true) {
            $other === null => 'null',
            is_bool($other) => $other ? 'true' : 'false',
            default => (string) $other,
        };

        return in_array($other, $values, true);
    }

    /** "items.*.from" used from items.2.to means items.2.from, as in Laravel. */
    private function sibling(string $field, string $attribute): string
    {
        $keys = explode('.', $attribute);
        $parts = explode('.', $field);

        foreach ($parts as $i => $part) {
            if ($part === '*' && isset($keys[$i])) {
                $parts[$i] = $keys[$i];
            }
        }

        return implode('.', $parts);
    }

    private function isDate(mixed $value): bool
    {
        if ($value instanceof DateTimeInterface) {
            return true;
        }

        if ((! is_string($value) && ! is_numeric($value)) || strtotime((string) $value) === false) {
            return false;
        }

        $date = date_parse((string) $value);

        return is_int($date['year']) && is_int($date['month']) && is_int($date['day']) && checkdate($date['month'], $date['day'], $date['year']);
    }

    private function matchesFormat(mixed $value, string $format): bool
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        $date = DateTime::createFromFormat('!'.$format, (string) $value);

        return $date !== false && $date->format($format) === (string) $value;
    }

    /** A number for numeric fields, a count for arrays, otherwise the length in characters. */
    private function size(mixed $value, array $names): float
    {
        return match (true) {
            is_numeric($value) && array_intersect(self::NUMERIC, $names) !== [] => (float) $value,
            is_array($value) => count($value),
            default => mb_strlen((string) $value),
        };
    }

    private function greaterThan(mixed $value, string $other, array $data, string $attribute, array $names): bool
    {
        [$exists, $compared] = $this->lookup($data, $this->sibling($other, $attribute));

        if (! $exists) {
            return is_numeric($value) && is_numeric($other) && (float) $value > (float) $other;
        }

        if (is_numeric($value) && is_numeric($compared)) {
            return (float) $value > (float) $compared;
        }

        return get_debug_type($value) === get_debug_type($compared) && $this->size($value, $names) > $this->size($compared, $names);
    }

    /** As Laravel: the parameter as a date first, then as a field; a missing other date passes. */
    private function afterOrEqual(mixed $value, string $other, array $data, string $attribute): bool
    {
        $second = $this->timestamp($other) ?? $this->timestamp($this->lookup($data, $this->sibling($other, $attribute))[1]);

        return $this->timestamp($value) >= $second;
    }

    /** A date as Laravel reads it for comparisons (Carbon::parse): "" is now, numbers are Unix times. */
    private function timestamp(mixed $value): ?int
    {
        try {
            return match (true) {
                $value instanceof DateTimeInterface => $value->getTimestamp(),
                is_int($value), is_float($value) => (int) $value,
                is_string($value) => (new DateTime($value))->getTimestamp(),
                default => null,
            };
        } catch (\Exception) {
            return null;
        }
    }

    /** Laravel's default English messages. */
    private function message(string $rule, array $parameters, string $label, array $names): string
    {
        // The wording follows the field's rules, not the value given.
        $kind = match (true) {
            array_intersect(self::NUMERIC, $names) !== [] => 'numeric',
            in_array('array', $names, true) => 'array',
            default => 'string',
        };
        $field = "The {$label} field";
        $first = $parameters[0] ?? '';

        return match ($rule) {
            'required' => "{$field} is required.",
            'present' => "{$field} must be present.",
            'required_if' => "{$field} is required when ".self::label($first).' is '.($parameters[1] ?? '').'.',
            'string' => "{$field} must be a string.",
            'numeric' => "{$field} must be a number.",
            'integer' => "{$field} must be an integer.",
            'boolean' => "{$field} must be true or false.",
            'array' => "{$field} must be an array.",
            'date' => "{$field} must be a valid date.",
            'date_format' => "{$field} must match the format {$first}.",
            'min' => match ($kind) {
                'numeric' => "{$field} must be at least {$first}.",
                'array' => "{$field} must have at least {$first} items.",
                default => "{$field} must be at least {$first} characters.",
            },
            'max' => match ($kind) {
                'numeric' => "{$field} must not be greater than {$first}.",
                'array' => "{$field} must not have more than {$first} items.",
                default => "{$field} must not be greater than {$first} characters.",
            },
            'size' => match ($kind) {
                'numeric' => "{$field} must be {$first}.",
                'array' => "{$field} must contain {$first} items.",
                default => "{$field} must be {$first} characters.",
            },
            'in' => "The selected {$label} is invalid.",
            'gt' => "{$field} must be greater than {$first}.",
            'after_or_equal' => "{$field} must be a date after or equal to {$first}.",
            'regex' => "{$field} format is invalid.",
        };
    }

    /** A field name as Laravel shows it: "due_date" as "due date"; fields from a "*" rule stay as they are. */
    private static function label(string $attribute): string
    {
        if (! ctype_lower($attribute)) {
            $attribute = preg_replace('/\s+/u', '', ucwords($attribute));
            $attribute = mb_strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1_', $attribute));
        }

        return str_replace('_', ' ', $attribute);
    }
}
