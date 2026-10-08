<?php

namespace BiztechEG\EasyPdfWord\Pdf;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Exceptions\DriverNotAvailable;
use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Pdf\Drivers\BrowsershotDriver;
use BiztechEG\EasyPdfWord\Pdf\Drivers\GotenbergDriver;
use BiztechEG\EasyPdfWord\Pdf\Drivers\MpdfDriver;
use Closure;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves PDF engines by name ("mpdf", "chromium", "gotenberg", or one
 * added with extend()) and falls back to another engine when the chosen
 * one is missing or fails.
 *
 * @method PdfDriver driver(string|null $driver = null)
 */
class PdfManager extends Manager
{
    private const ALIASES = ['chromium' => 'browsershot', 'chrome' => 'browsershot'];

    public function getDefaultDriver(): string
    {
        return $this->config->get('easy-pdf-word.pdf.driver', 'mpdf');
    }

    /** Engine names are matched without case, as driver() lowercases them. */
    public function extend($driver, Closure $callback)
    {
        return parent::extend(strtolower($driver), $callback);
    }

    public function normalize(?string $driver): string
    {
        $driver = strtolower($driver ?? $this->getDefaultDriver());

        return self::ALIASES[$driver] ?? $driver;
    }

    public function driver($driver = null)
    {
        return parent::driver($this->normalize($driver));
    }

    /**
     * Render with the given engine, or the fallback engine if it is not
     * installed or throws.
     *
     * @return array{0: string, 1: string} PDF bytes and the engine that made them
     */
    public function render(string $html, PdfOptions $options, ?string $driver = null, ?callable $htmlFor = null): array
    {
        $name = $this->normalize($driver);
        $fallback = $this->config->get('easy-pdf-word.pdf.fallback');
        $fallback = $fallback ? $this->normalize($fallback) : null;

        try {
            $engine = $this->driver($name);

            if (! $engine->isAvailable()) {
                throw match ($name) {
                    'mpdf' => DriverNotAvailable::missingPackage('mpdf', 'mpdf/mpdf'),
                    'browsershot' => DriverNotAvailable::missingPackage('browsershot', 'spatie/browsershot'),
                    'gotenberg' => new DriverNotAvailable('The [gotenberg] engine needs the URL of a Gotenberg server in DOC_GOTENBERG_URL.'),
                    default => new DriverNotAvailable("The [{$name}] PDF engine is not available."),
                };
            }
        } catch (Throwable $e) {
            return $this->fallback($name, $fallback, $e, $html, $options, $htmlFor);
        }

        // Errors in the document itself (a view, invalid data) are not the
        // engine's fault, so they are thrown rather than retried.
        $content = $htmlFor ? $htmlFor($engine) : $html;

        try {
            $pdf = $engine->render($content, $options);
        } catch (Throwable $e) {
            return $this->fallback($name, $fallback, $e, $html, $options, $htmlFor);
        }

        return [$this->protect($pdf, $engine, $options), $name];
    }

    /** @return array{0: string, 1: string} */
    private function fallback(string $name, ?string $fallback, Throwable $e, string $html, PdfOptions $options, ?callable $htmlFor): array
    {
        if ($fallback === null || $fallback === $name) {
            throw $e;
        }

        // A fallback that is not installed would only hide the real error.
        try {
            $engine = $this->driver($fallback);
        } catch (Throwable) {
            throw $e;
        }

        if (! $engine->isAvailable()) {
            throw $e;
        }

        // Kept short: Browsershot's message holds the whole command, with the header and footer.
        Log::warning("easy-pdf-word: [{$name}] failed, falling back to [{$fallback}]: ".Str::limit($e->getMessage(), 300));

        return [$this->protect($engine->render($htmlFor ? $htmlFor($engine) : $html, $options), $engine, $options), $fallback];
    }

    /** mPDF encrypts as it renders; other engines' files get a password afterwards. */
    private function protect(string $pdf, PdfDriver $engine, PdfOptions $options): string
    {
        if ($options->protection === null || $engine instanceof MpdfDriver) {
            return $pdf;
        }

        return (new PdfProtector($this->engineConfig('mpdf')['temp_dir'] ?? null))->protect($pdf, $options);
    }

    public function engineConfig(string $name): array
    {
        return (array) $this->config->get("easy-pdf-word.pdf.drivers.{$name}", []);
    }

    protected function createMpdfDriver(): PdfDriver
    {
        return new MpdfDriver($this->container->make(FontRegistry::class), $this->engineConfig('mpdf'));
    }

    protected function createBrowsershotDriver(): PdfDriver
    {
        return new BrowsershotDriver($this->engineConfig('browsershot'), $this->container->make(FontRegistry::class));
    }

    protected function createGotenbergDriver(): PdfDriver
    {
        return new GotenbergDriver($this->container->make(Http::class), $this->engineConfig('gotenberg'), $this->container->make(FontRegistry::class));
    }
}
