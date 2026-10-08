<?php

namespace BiztechEG\EasyPdfWord\Contracts;

use BiztechEG\EasyPdfWord\Pdf\PdfOptions;

interface PdfDriver
{
    /**
     * Turn a full HTML document into PDF bytes.
     */
    public function render(string $html, PdfOptions $options): string;

    /**
     * Whether the engine's package or service is installed, so the manager
     * can fall back before trying to render.
     */
    public function isAvailable(): bool;

    /**
     * Whether the engine loads fonts from CSS @font-face (Chromium) rather
     * than from its own font configuration (mPDF).
     */
    public function usesCssFonts(): bool;
}
