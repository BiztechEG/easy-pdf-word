<?php

namespace BiztechEG\EasyPdfWord\Support;

/**
 * Colours from data and themes, checked before they reach CSS or Word, so
 * a value like "red; background: url(...)" cannot add styles or requests.
 */
final class Color
{
    /** The 148 CSS colour names, so "red" means the same in Word as in the PDF. */
    private const NAMES = [
        'aliceblue' => 'F0F8FF', 'antiquewhite' => 'FAEBD7', 'aqua' => '00FFFF', 'aquamarine' => '7FFFD4',
        'azure' => 'F0FFFF', 'beige' => 'F5F5DC', 'bisque' => 'FFE4C4', 'black' => '000000',
        'blanchedalmond' => 'FFEBCD', 'blue' => '0000FF', 'blueviolet' => '8A2BE2', 'brown' => 'A52A2A',
        'burlywood' => 'DEB887', 'cadetblue' => '5F9EA0', 'chartreuse' => '7FFF00', 'chocolate' => 'D2691E',
        'coral' => 'FF7F50', 'cornflowerblue' => '6495ED', 'cornsilk' => 'FFF8DC', 'crimson' => 'DC143C',
        'cyan' => '00FFFF', 'darkblue' => '00008B', 'darkcyan' => '008B8B', 'darkgoldenrod' => 'B8860B',
        'darkgray' => 'A9A9A9', 'darkgreen' => '006400', 'darkgrey' => 'A9A9A9', 'darkkhaki' => 'BDB76B',
        'darkmagenta' => '8B008B', 'darkolivegreen' => '556B2F', 'darkorange' => 'FF8C00', 'darkorchid' => '9932CC',
        'darkred' => '8B0000', 'darksalmon' => 'E9967A', 'darkseagreen' => '8FBC8F', 'darkslateblue' => '483D8B',
        'darkslategray' => '2F4F4F', 'darkslategrey' => '2F4F4F', 'darkturquoise' => '00CED1',
        'darkviolet' => '9400D3', 'deeppink' => 'FF1493', 'deepskyblue' => '00BFFF', 'dimgray' => '696969',
        'dimgrey' => '696969', 'dodgerblue' => '1E90FF', 'firebrick' => 'B22222', 'floralwhite' => 'FFFAF0',
        'forestgreen' => '228B22', 'fuchsia' => 'FF00FF', 'gainsboro' => 'DCDCDC', 'ghostwhite' => 'F8F8FF',
        'gold' => 'FFD700', 'goldenrod' => 'DAA520', 'gray' => '808080', 'green' => '008000',
        'greenyellow' => 'ADFF2F', 'grey' => '808080', 'honeydew' => 'F0FFF0', 'hotpink' => 'FF69B4',
        'indianred' => 'CD5C5C', 'indigo' => '4B0082', 'ivory' => 'FFFFF0', 'khaki' => 'F0E68C',
        'lavender' => 'E6E6FA', 'lavenderblush' => 'FFF0F5', 'lawngreen' => '7CFC00', 'lemonchiffon' => 'FFFACD',
        'lightblue' => 'ADD8E6', 'lightcoral' => 'F08080', 'lightcyan' => 'E0FFFF', 'lightgoldenrodyellow' => 'FAFAD2',
        'lightgray' => 'D3D3D3', 'lightgreen' => '90EE90', 'lightgrey' => 'D3D3D3', 'lightpink' => 'FFB6C1',
        'lightsalmon' => 'FFA07A', 'lightseagreen' => '20B2AA', 'lightskyblue' => '87CEFA',
        'lightslategray' => '778899', 'lightslategrey' => '778899', 'lightsteelblue' => 'B0C4DE',
        'lightyellow' => 'FFFFE0', 'lime' => '00FF00', 'limegreen' => '32CD32', 'linen' => 'FAF0E6',
        'magenta' => 'FF00FF', 'maroon' => '800000', 'mediumaquamarine' => '66CDAA', 'mediumblue' => '0000CD',
        'mediumorchid' => 'BA55D3', 'mediumpurple' => '9370DB', 'mediumseagreen' => '3CB371',
        'mediumslateblue' => '7B68EE', 'mediumspringgreen' => '00FA9A', 'mediumturquoise' => '48D1CC',
        'mediumvioletred' => 'C71585', 'midnightblue' => '191970', 'mintcream' => 'F5FFFA', 'mistyrose' => 'FFE4E1',
        'moccasin' => 'FFE4B5', 'navajowhite' => 'FFDEAD', 'navy' => '000080', 'oldlace' => 'FDF5E6',
        'olive' => '808000', 'olivedrab' => '6B8E23', 'orange' => 'FFA500', 'orangered' => 'FF4500',
        'orchid' => 'DA70D6', 'palegoldenrod' => 'EEE8AA', 'palegreen' => '98FB98', 'paleturquoise' => 'AFEEEE',
        'palevioletred' => 'DB7093', 'papayawhip' => 'FFEFD5', 'peachpuff' => 'FFDAB9', 'peru' => 'CD853F',
        'pink' => 'FFC0CB', 'plum' => 'DDA0DD', 'powderblue' => 'B0E0E6', 'purple' => '800080',
        'rebeccapurple' => '663399', 'red' => 'FF0000', 'rosybrown' => 'BC8F8F', 'royalblue' => '4169E1',
        'saddlebrown' => '8B4513', 'salmon' => 'FA8072', 'sandybrown' => 'F4A460', 'seagreen' => '2E8B57',
        'seashell' => 'FFF5EE', 'sienna' => 'A0522D', 'silver' => 'C0C0C0', 'skyblue' => '87CEEB',
        'slateblue' => '6A5ACD', 'slategray' => '708090', 'slategrey' => '708090', 'snow' => 'FFFAFA',
        'springgreen' => '00FF7F', 'steelblue' => '4682B4', 'tan' => 'D2B48C', 'teal' => '008080',
        'thistle' => 'D8BFD8', 'tomato' => 'FF6347', 'turquoise' => '40E0D0', 'violet' => 'EE82EE',
        'wheat' => 'F5DEB3', 'white' => 'FFFFFF', 'whitesmoke' => 'F5F5F5', 'yellow' => 'FFFF00',
        'yellowgreen' => '9ACD32',
    ];

    /** #RGB, #RRGGBB, #RRGGBBAA, rgb()/rgba()/hsl() with numbers, or a name such as "red". */
    public static function isValid(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,20}|(rgb|hsl)a?\([\d\s.,%\/]+\))\z/i', $value) === 1;
    }

    public static function css(mixed $value, ?string $fallback = null): ?string
    {
        return self::isValid($value) ? $value : $fallback;
    }

    /**
     * RRGGBB for Word, from #RGB, #RRGGBB, #RRGGBBAA, rgb(), hsl() (the alpha
     * is dropped) or a CSS colour name. Null for anything else.
     */
    public static function hex(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $rgb = self::rgb(trim($value));

        return $rgb === null ? null : vsprintf('%02X%02X%02X', $rgb);
    }

    /** A colour mixed with white: tint('#0F766E', 0.9) is a pale teal for a background. */
    public static function tint(mixed $value, float $white, string $fallback = '#F3F4F6'): string
    {
        $rgb = is_string($value) ? self::rgb(trim($value)) : null;

        if ($rgb === null) {
            return $fallback;
        }

        $white = max(0.0, min(1.0, $white));

        return '#'.vsprintf('%02X%02X%02X', array_map(fn ($c) => (int) round($c + (255 - $c) * $white), $rgb));
    }

    /** @return array{0: int, 1: int, 2: int}|null */
    private static function rgb(string $value): ?array
    {
        if (isset(self::NAMES[$name = strtolower($value)])) {
            return array_map('hexdec', str_split(self::NAMES[$name], 2));
        }

        if (preg_match('/^#?([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})\z/i', $value, $m)) {
            $hex = strlen($m[1]) <= 4 ? preg_replace('/(.)/', '$1$1', $m[1]) : $m[1];

            return array_map('hexdec', str_split(substr($hex, 0, 6), 2));
        }

        if (! preg_match('/^(rgb|hsl)a?\(([\d\s.,%\/]+)\)\z/i', $value, $m)) {
            return null;
        }

        $parts = preg_split('/[\s,\/]+/', trim($m[2]));

        if (count($parts) < 3) {
            return null;
        }

        $number = fn (string $part, float $max) => str_ends_with($part, '%') ? (float) $part / 100 * $max : (float) $part;

        if (strtolower($m[1]) === 'rgb') {
            return array_map(fn ($part) => (int) round(max(0, min(255, $number($part, 255)))), array_slice($parts, 0, 3));
        }

        $h = fmod((float) $parts[0], 360) / 360;
        $s = max(0, min(1, $number(str_ends_with($parts[1], '%') ? $parts[1] : $parts[1].'%', 1)));
        $l = max(0, min(1, $number(str_ends_with($parts[2], '%') ? $parts[2] : $parts[2].'%', 1)));
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $channel = function (float $t) use ($p, $q): int {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);
            $v = match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };

            return (int) round($v * 255);
        };

        return [$channel($h + 1 / 3), $channel($h), $channel($h - 1 / 3)];
    }
}
