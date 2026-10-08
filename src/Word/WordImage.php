<?php

namespace BiztechEG\EasyPdfWord\Word;

/**
 * Image bytes Word can show. Word takes JPEG, PNG and GIF; WebP and BMP
 * are redrawn as PNG with GD. SVG needs a browser to draw, so it is left
 * out of Word files (PDFs still show it).
 */
final class WordImage
{
    /**
     * The bytes of a source that DocContext::image() allowed: a data URI
     * or an allowed URL. Null when Word cannot show it.
     */
    public static function load(string $source): ?string
    {
        $bytes = match (true) {
            str_starts_with($source, 'data:') => base64_decode(substr($source, strpos($source, ',') + 1), true),
            // Fetched here rather than by PhpWord, so redirects are not
            // followed (they could lead past the allowed hosts) and a slow
            // server cannot hold the request.
            preg_match('#^https?://#i', $source) === 1 => @file_get_contents($source, false, stream_context_create([
                'http' => ['timeout' => 10, 'follow_location' => 0],
            ])),
            default => null,
        };

        return is_string($bytes) && $bytes !== '' ? self::forWord($bytes) : null;
    }

    public static function forWord(string $bytes): ?string
    {
        $type = (@getimagesizefromstring($bytes) ?: [])[2] ?? null;

        if (in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            return $bytes;
        }

        $image = $type !== null ? @imagecreatefromstring($bytes) : false;

        if ($image === false) {
            return null;
        }

        imagesavealpha($image, true);
        ob_start();
        imagepng($image);

        return ob_get_clean() ?: null;
    }
}
