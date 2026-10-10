<?php

namespace BiztechEG\EasyPdfWord\Pdf;

use BiztechEG\EasyPdfWord\Support\Color;
use BiztechEG\EasyPdfWord\Support\Html;

/**
 * The watermark for Chromium engines: a fixed element, which Chromium prints
 * on every page, centred and turned like mPDF's.
 */
class Watermark
{
    /** Add the watermark at the end of the document's body. */
    public static function inject(string $html, PdfOptions $options): string
    {
        if ($options->watermark === null) {
            return $html;
        }

        $mark = self::html($options);
        $count = 0;
        $html = preg_replace('/<\/body>/i', $mark.'</body>', $html, 1, $count) ?? $html;

        return $count === 1 ? $html : $html.$mark;
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
