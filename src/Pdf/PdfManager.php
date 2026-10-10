<?php

namespace BiztechEG\EasyPdfWord\Pdf;

use BiztechEG\EasyPdfWord\Contracts\HttpClient;
use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Exceptions\DriverNotAvailable;
use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Pdf\Drivers\BrowsershotDriver;
use BiztechEG\EasyPdfWord\Pdf\Drivers\GotenbergDriver;
use BiztechEG\EasyPdfWord\Pdf\Drivers\MpdfDriver;
use BiztechEG\EasyPdfWord\Support\CurlHttpClient;
use BiztechEG\EasyPdfWord\Support\Data;
use Closure;
use InvalidArgumentException;
use Throwable;

/**
 * Resolves PDF engines by name ("mpdf", "chromium", "gotenberg", or one
 * added with extend()) and falls back to another engine when the chosen
 * one is missing or fails.
 */
class PdfManager
{
    private const ALIASES = ['chromium' => 'browsershot', 'chrome' => 'browsershot'];

    private const BUILT_IN = ['mpdf', 'browsershot', 'gotenberg'];

    /** @var Closure(): array */
    private Closure $config;

    /** @var Closure(): FontRegistry */
    private Closure $fonts;

    private HttpClient $http;

    /** @var Closure(string): void */
    private Closure $warn;

    /** @var array<string, Closure> */
    private array $customCreators = [];

    /** @var array<string, PdfDriver> */
    private array $drivers = [];

    /**
     * @param  array|Closure(): array  $config  the package settings (config/easy-pdf-word.php); a closure is read on every use, so later changes apply
     * @param  FontRegistry|Closure(): FontRegistry|null  $fonts
     * @param  Closure(string): void|null  $warn  told when an engine fails and the fallback renders; error_log() by default
     * @param  mixed  $creatorArgument  what engines added with extend() receive (the app, in Laravel)
     */
    public function __construct(
        array|Closure $config = [],
        FontRegistry|Closure|null $fonts = null,
        ?HttpClient $http = null,
        ?Closure $warn = null,
        private mixed $creatorArgument = null,
    ) {
        $this->config = $config instanceof Closure ? $config : fn () => $config;
        $fonts ??= new FontRegistry((array) Data::get($this->config(), 'fonts.custom', []));
        $this->fonts = $fonts instanceof Closure ? $fonts : fn () => $fonts;
        $this->http = $http ?? new CurlHttpClient;
        $this->warn = $warn ?? fn (string $message) => error_log($message);
    }

    public function getDefaultDriver(): string
    {
        return (string) Data::get($this->config(), 'pdf.driver', 'mpdf');
    }

    /** Add an engine: extend('my-engine', fn ($app) => new MyDriver). Names are matched without case. */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[strtolower($driver)] = $callback;

        return $this;
    }

    /** Whether an engine of this name is built in or was added with extend(). */
    public function has(string $driver): bool
    {
        $driver = $this->normalize($driver);

        return isset($this->customCreators[$driver]) || in_array($driver, self::BUILT_IN, true);
    }

    public function normalize(?string $driver): string
    {
        $driver = strtolower($driver ?? $this->getDefaultDriver());

        return self::ALIASES[$driver] ?? $driver;
    }

    /** The engine of this name, made once and kept. */
    public function driver(?string $driver = null): PdfDriver
    {
        $driver = $this->normalize($driver);

        return $this->drivers[$driver] ??= $this->create($driver);
    }

    /** @return array<string, PdfDriver> the engines made so far */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /** Make engines again on next use, e.g. after a config change. */
    public function forgetDrivers(): static
    {
        $this->drivers = [];

        return $this;
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
        $fallback = Data::get($this->config(), 'pdf.fallback');
        $fallback = $fallback ? $this->normalize($fallback) : null;

        // A misspelt name is a mistake in the app, not an engine that failed.
        if (! $this->has($name)) {
            throw new \InvalidArgumentException("Unknown PDF engine [{$name}]. Use mpdf, chromium, gotenberg or a name added with Doc::extend().");
        }

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
        ($this->warn)("easy-pdf-word: [{$name}] failed, falling back to [{$fallback}]: ".self::limit($e->getMessage(), 300));

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
        return (array) Data::get($this->config(), "pdf.drivers.{$name}", []);
    }

    private function config(): array
    {
        return (array) ($this->config)();
    }

    private function create(string $driver): PdfDriver
    {
        if (isset($this->customCreators[$driver])) {
            return $this->customCreators[$driver]($this->creatorArgument);
        }

        return match ($driver) {
            'mpdf' => new MpdfDriver(($this->fonts)(), $this->engineConfig('mpdf')),
            'browsershot' => new BrowsershotDriver($this->engineConfig('browsershot'), ($this->fonts)()),
            'gotenberg' => new GotenbergDriver($this->http, $this->engineConfig('gotenberg'), ($this->fonts)()),
            default => throw new InvalidArgumentException("Driver [{$driver}] not supported."),
        };
    }

    /** At most $limit characters wide, then "...". */
    private static function limit(string $text, int $limit): string
    {
        return mb_strwidth($text, 'UTF-8') <= $limit ? $text : rtrim(mb_strimwidth($text, 0, $limit, '', 'UTF-8')).'...';
    }

    /** Other calls go to the default engine, e.g. $manager->isAvailable(). */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }
}
