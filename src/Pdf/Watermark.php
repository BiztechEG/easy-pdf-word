<?php

namespace BiztechEG\EasyPdfWord\Pdf;

use BiztechEG\EasyPdfWord\Support\Color;
use BiztechEG\EasyPdfWord\Support\Html;

/**
 * The watermark for Chromium engines: a fixed element, which Chromium prints
 * on every page, centred and turned like mPDF's. An image watermark is a
 * fixed image in the middle of every page.
 */
class Watermark
{
    /** Add the watermark at the end of the document's body. */
    public static function inject(string $html, PdfOptions $options): string
    {
        if ($options->watermark === null && $options->watermarkImage === null) {
            return $html;
        }

        $mark = ($options->watermarkImage === null ? '' : self::imageHtml($options->watermarkImage))
            .($options->watermark === null ? '' : self::html($options));
        $count = 0;
        $html = preg_replace('/<\/body>/i', $mark.'</body>', $html, 1, $count) ?? $html;

        return $count === 1 ? $html : $html.$mark;
    }

    /**
     * The image watermark's size in mm: its width, and the height its
     * proportions give.
     *
     * @return array{0: float, 1: float}
     */
    public static function imageSize(array $mark): array
    {
        $bytes = (string) base64_decode(substr($mark['src'], strpos($mark['src'], ',') + 1), true);
        [$width, $height] = @getimagesizefromstring($bytes) ?: self::svgSize($bytes);

        return [(float) $mark['width'], round($mark['width'] * $height / max(1, $width), 2)];
    }

    public static function imageHtml(array $mark): string
    {
        [$width, $height] = self::imageSize($mark);

        return '<div aria-hidden="true" style="position: fixed; top: 0; right: 0; bottom: 0; left: 0; display: flex; '
            .'align-items: center; justify-content: center; pointer-events: none; z-index: 2147483647;">'
            .'<img src="'.Html::escape($mark['src']).'" alt="" style="width: '.$width.'mm; height: '.$height.'mm; opacity: '.(float) $mark['opacity'].';"></div>';
    }

    /** @return array{0: float, 1: float} an SVG's viewBox or width and height; square when it has none */
    private static function svgSize(string $svg): array
    {
        if (preg_match('/viewBox\s*=\s*["\'][-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $svg, $m) && (float) $m[1] > 0) {
            return [(float) $m[1], (float) $m[2]];
        }

        if (preg_match('/<svg\b[^>]*\swidth\s*=\s*["\']([\d.]+)[^>]*\sheight\s*=\s*["\']([\d.]+)/i', $svg, $m) && (float) $m[1] > 0) {
            return [(float) $m[1], (float) $m[2]];
        }

        return [1.0, 1.0];
    }

    public static function html(PdfOptions $options): string
    {
        ['text' => $text, 'opacity' => $opacity, 'color' => $color] = $options->watermark;
        [$width, $height] = $options->paperSize();

        // Like mPDF: as large as fits along the shorter page side, at most 96pt.
        $size = round(min(96, min($width, $height) / (0.55 * max(1, mb_strlen($text))) * 2.835), 1);

        return '<div aria-hidden="true" style="position: fixed; top: 0; right: 0; bottom: 0; left: 0; display: flex; '
            .'align-items: center; justify-content: center; pointer-events: none; z-index: 2147483647;">'
            .'<div dir="'.$options->direction.'" style="transform: rotate(-45deg); white-space: nowrap; font-weight: bold; '
            .'font-family: \''.Html::escape($options->font).'\', sans-serif; font-size: '.$size.'pt; color: '.Color::css($color, '#000000')
            .'; opacity: '.(float) $opacity.';">'
            .Html::escape($text).'</div></div>';
    }
}
