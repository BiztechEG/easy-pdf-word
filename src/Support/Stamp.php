<?php

namespace BiztechEG\EasyPdfWord\Support;

/**
 * A table cell's "overlay": a stamp drawn over the cell, behind its text.
 * Sizes and offsets are in mm; the offset is [start, down] from the cell's
 * start-top corner, so a positive start moves toward the right in Arabic.
 *
 * @internal
 */
final class Stamp
{
    /** The PDF stamp is redrawn at this resolution. */
    private const DPI = 300;

    /** Sizes and offsets are kept within a page. */
    private const MAX_MM = 300;

    /**
     * The overlay's options, read once: its size in mm, kept in proportion
     * unless "stretch", and its offset.
     *
     * @return array{width: float, height: float, start: float, down: float}|null null when the bytes are not an image GD can draw
     */
    public static function measure(array $overlay, string $bytes): ?array
    {
        $size = @getimagesizefromstring($bytes);

        if (! $size || $size[0] < 1 || $size[1] < 1 || ! in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_BMP], true)) {
            return null;
        }

        $clamp = fn (mixed $mm, float $min) => is_numeric($mm) ? (float) max($min, min(self::MAX_MM, (float) $mm)) : null;
        $width = $clamp($overlay['width'] ?? null, 1);
        $height = $clamp($overlay['height'] ?? null, 1);
        $ratio = $size[1] / $size[0];

        [$width, $height] = match (true) {
            $width !== null && $height !== null && ! empty($overlay['stretch']) => [$width, $height],
            $width !== null && $height !== null => min($width, $height / $ratio) === $width ? [$width, $width * $ratio] : [$height / $ratio, $height],
            $height !== null => [$height / $ratio, $height],
            default => [$width ??= 30.0, $width * $ratio],
        };

        $offset = is_array($overlay['offset'] ?? null) ? array_values($overlay['offset']) : [];

        return [
            'width' => $width,
            'height' => $height,
            'start' => $clamp($offset[0] ?? 0, -self::MAX_MM) ?? 0.0,
            'down' => $clamp($offset[1] ?? 0, -self::MAX_MM) ?? 0.0,
        ];
    }

    /**
     * The stamp for a PDF cell background, which is anchored at the cell's
     * start-top corner: the image redrawn at 300 dpi with the offset as
     * transparent space before and above it. A background stays inside its
     * cell, so what an offset moves past the cell's edge is cut off.
     *
     * @return array{uri: string, width: float, height: float}|null the PNG as a data URI, and its size in mm
     */
    public static function forPdf(string $bytes, array $box, bool $rtl): ?array
    {
        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return null;
        }

        $px = fn (float $mm) => (int) round($mm * self::DPI / 25.4);
        [$width, $height, $start, $down] = [$px($box['width']), $px($box['height']), $px($box['start']), $px($box['down'])];
        $canvasWidth = $width + $start;
        $canvasHeight = $height + $down;

        if ($width < 1 || $height < 1 || $canvasWidth < 1 || $canvasHeight < 1) {
            return null;
        }

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        imagealphablending($canvas, false);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagesavealpha($canvas, true);
        imagealphablending($canvas, true);
        // In RTL the canvas sits at the cell's right edge, so the space is on its right.
        imagecopyresampled($canvas, $source, $rtl ? 0 : $start, $down, 0, 0, $width, $height, imagesx($source), imagesy($source));

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        return [
            'uri' => 'data:image/png;base64,'.base64_encode($png),
            'width' => $canvasWidth * 25.4 / self::DPI,
            'height' => $canvasHeight * 25.4 / self::DPI,
        ];
    }

    /** The CSS of a PDF cell with the stamp as its background. */
    public static function css(array $stamp, bool $rtl): string
    {
        // mPDF sizes a cell background by its resolution, browsers by background-size.
        return "background-image: url('".$stamp['uri']."'); background-repeat: no-repeat; background-position: ".($rtl ? '100%' : '0').' 0; '
            .'background-image-resolution: '.self::DPI.'dpi; background-size: '.round($stamp['width'], 2).'mm '.round($stamp['height'], 2).'mm;';
    }
}
