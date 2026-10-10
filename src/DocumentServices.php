<?php

namespace BiztechEG\EasyPdfWord;

use BiztechEG\EasyPdfWord\Contracts\Validator;
use BiztechEG\EasyPdfWord\Contracts\ViewRenderer;
use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Pdf\PdfManager;
use BiztechEG\EasyPdfWord\Support\Data;
use Closure;

/**
 * What a document needs to render: engines, fonts, views, validation and
 * the settings (the same keys as config/easy-pdf-word.php). Plain PHP gets
 * one from EasyPdfWord::create(); Laravel builds one from its container.
 */
final class DocumentServices
{
    /**
     * @param  array|Closure(): array  $config  a closure is read on every use, so config changed at runtime applies
     * @param  (Closure(): ?string)|null  $appLocale  the locale when neither the document nor "locale" sets one
     */
    public function __construct(
        public readonly PdfManager $pdf,
        public readonly FontRegistry $fonts,
        public readonly ViewRenderer $views,
        public readonly Validator $validator,
        private readonly array|Closure $config = [],
        private readonly ?Closure $appLocale = null,
    ) {}

    /** A setting by "dot.notation" key: config('pdf.paper'). */
    public function config(string $key, mixed $default = null): mixed
    {
        return Data::get($this->config instanceof Closure ? ($this->config)() : $this->config, $key, $default);
    }

    public function appLocale(): string
    {
        return ($this->appLocale === null ? null : ($this->appLocale)()) ?? 'en';
    }
}
