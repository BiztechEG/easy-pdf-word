<?php

namespace BiztechEG\EasyPdfWord\Support;

use ArrayAccess;
use Closure;

/**
 * Array helpers without Laravel.
 */
final class Data
{
    /**
     * A value by "dot.notation" key: Data::get($theme, 'company.name').
     * A key that exists as written ("a.b") wins over the nested path.
     */
    public static function get(array|ArrayAccess $data, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $data;
        }

        if (self::exists($data, $key)) {
            return $data[$key];
        }

        if (! is_string($key) || ! str_contains($key, '.')) {
            return $default instanceof Closure ? $default() : $default;
        }

        foreach (explode('.', $key) as $segment) {
            if ((is_array($data) || $data instanceof ArrayAccess) && self::exists($data, $segment)) {
                $data = $data[$segment];
            } else {
                return $default instanceof Closure ? $default() : $default;
            }
        }

        return $data;
    }

    private static function exists(array|ArrayAccess $data, string|int $key): bool
    {
        return $data instanceof ArrayAccess ? $data->offsetExists($key) : array_key_exists($key, $data);
    }
}
