<?php

namespace BiztechEG\EasyPdfWord\View;

use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Support\Html;

/**
 * The HTML page around a document body: language, direction, fonts and the
 * base styles every template shares. The same page as the <x-doc::layout>
 * Blade component, byte for byte, so a template looks the same in both.
 */
class PageLayout
{
    /**
     * @param  string  $body  the page content (HTML)
     * @param  string  $styles  extra tags for the head, such as a template's own <style>
     */
    public static function render(DocContext $doc, string $body, ?string $title = null, string $styles = ''): string
    {
        $e = static fn (mixed $value): string => Html::escape($value);
        $fonts = $doc->usesCssFonts() ? '                '.$doc->fontCss."\n" : '';
        $styles = trim($styles);
        $body = trim($body);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="{$e($doc->locale)}" dir="{$e($doc->direction)}">
            <head>
                <meta charset="utf-8">
                <title>{$e($title)}</title>
                <style>
            {$fonts}                body {
                        font-family: '{$e($doc->font)}', sans-serif;
                        direction: {$e($doc->direction)};
                        color: {$e($doc->theme('text', '#1F2937'))};
                        font-size: 10.5pt;
                        line-height: 1.5;
                        margin: 0;
                    }
                    table { border-collapse: collapse; width: 100%; }
                    th, td { vertical-align: top; }
                    h1, h2, h3 { margin: 0; line-height: 1.3; }
                    p { margin: 0 0 6pt 0; }
                    .text-start { text-align: {$e($doc->start())}; }
                    .text-end { text-align: {$e($doc->end())}; }
                    .text-center { text-align: center; }
                    .muted { color: {$e($doc->theme('muted', '#6B7280'))}; }
                    .ltr { direction: ltr; unicode-bidi: embed; }
                    .nowrap { white-space: nowrap; }
                </style>
                {$styles}
            </head>
            <body>
            {$body}
            </body>
            </html>

            HTML;
    }
}
