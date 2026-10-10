<?php

namespace BiztechEG\EasyPdfWord\Support;

use BackedEnum;

/**
 * HTML escaping without Laravel: the same output as Laravel's e().
 */
final class Html
{
    /** Escape text for HTML. Values that are already HTML (toHtml()) pass through. */
    public static function escape(mixed $value, bool $doubleEncode = true): string
    {
        if (is_object($value) && method_exists($value, 'toHtml')) {
            return (string) $value->toHtml();
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }
}
