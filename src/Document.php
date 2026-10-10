<?php

namespace BiztechEG\EasyPdfWord;

use BiztechEG\EasyPdfWord\Arabic\Direction;
use BiztechEG\EasyPdfWord\Arabic\Numerals;
use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Builder\HtmlRenderer;
use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Exceptions\WordNotSupported;
use BiztechEG\EasyPdfWord\Output\File;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Support\Color;
use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Support\Html;
use BiztechEG\EasyPdfWord\Support\Locale;
use BiztechEG\EasyPdfWord\Templates\Template;
use BiztechEG\EasyPdfWord\View\PageLayout;
use BiztechEG\EasyPdfWord\Word\DocxTemplateFiller;
use BiztechEG\EasyPdfWord\Word\WordRenderer;
use BadMethodCallException;
use InvalidArgumentException;
use LogicException;

/**
 * A document being configured, without a framework. Every setter returns
 * $this; ->pdf() and ->word() turn it into a file. In Laravel the Doc
 * facade hands out PendingDocument, which adds queues, fakes and responses.
 *
 *   $docs = EasyPdfWord::create(['locale' => 'ar']);
 *   $docs->template('invoice', $data)->pdf()->save(__DIR__.'/invoice.pdf');
 *   $docs->make()->heading('تقرير')->table($rows)->word()->send('report.docx');
 *
 * @method static heading(string $text, int $level = 1, array $style = [])
 * @method static paragraph(string|array $text, array $style = [])
 * @method static table(array $rows, array $options = [])
 * @method static image(string $source, float $widthMm = 40, string $align = 'start')
 * @method static qr(string $value, float $sizeMm = 30, string $align = 'start')
 * @method static spacer(float $heightMm = 5)
 * @method static pageBreak()
 * @method static line(?string $color = null)
 */
class Document
{
    protected array $data = [];

    protected ?string $locale = null;

    protected ?string $direction = null;

    protected ?string $numerals = null;

    protected array $theme = [];

    protected ?string $font = null;

    /** @var string|array{0: float, 1: float}|null */
    protected string|array|null $paper = null;

    protected ?string $orientation = null;

    protected ?array $margins = null;

    protected ?string $header = null;

    protected ?string $footer = null;

    protected ?string $driver = null;

    protected ?string $title = null;

    protected bool $validate = true;

    protected ?array $watermark = null;

    protected ?array $protection = null;

    /** What ->password() may allow readers to do (mPDF's permission names). */
    public const PERMISSIONS = ['print', 'print-highres', 'copy', 'modify', 'annot-forms', 'fill-forms', 'extract', 'assemble'];

    private const THEME_COLORS = ['primary' => '#0F766E', 'text' => '#1F2937', 'muted' => '#6B7280', 'border' => '#E5E7EB'];

    /** Validated and prepared template data, kept until the data or theme changes. */
    protected ?array $prepared = null;

    final protected function __construct(
        protected DocumentServices $services,
        protected ?Template $template = null,
        protected ?string $view = null,
        protected ?string $html = null,
        protected ?DocumentBuilder $builder = null,
    ) {}

    public static function forTemplate(Template $template, DocumentServices $services): static
    {
        return new static($services, template: $template);
    }

    /** A view by name: a Laravel view, or a .php file under the folders given to the view renderer. */
    public static function forView(string $view, DocumentServices $services): static
    {
        return new static($services, view: $view);
    }

    public static function forHtml(string $html, DocumentServices $services): static
    {
        return new static($services, html: $html);
    }

    public static function forBuilder(DocumentBuilder $builder, DocumentServices $services): static
    {
        return new static($services, builder: $builder);
    }

    /**
     * Data for the template or view. Each call replaces top-level keys:
     * ->data(['invoice' => [...]]) replaces the whole "invoice" array.
     */
    public function data(array $data): static
    {
        $this->data = array_replace($this->data, $data);
        $this->prepared = null;

        return $this;
    }

    public function with(string|array $key, mixed $value = null): static
    {
        return $this->data(is_array($key) ? $key : [$key => $value]);
    }

    /**
     * Sets language and, unless ->direction() is called, the direction (ar => rtl).
     * Takes a locale name such as "ar", "en" or "ar_EG".
     */
    public function locale(string $locale): static
    {
        if (! Locale::isValid($locale)) {
            throw new InvalidArgumentException('Invalid locale ['.substr($locale, 0, 40).'], expected a name such as "ar", "en" or "ar_EG".');
        }

        $this->locale = $locale;

        return $this;
    }

    public function direction(string $direction): static
    {
        $this->direction = strtolower($direction) === 'rtl' ? 'rtl' : 'ltr';

        return $this;
    }

    public function rtl(): static
    {
        return $this->direction('rtl');
    }

    public function ltr(): static
    {
        return $this->direction('ltr');
    }

    /** "arabic" (٠١٢٣) or "latin" (0123) digits in the document text. */
    public function numerals(string $style): static
    {
        $this->numerals = Numerals::normalizeStyle($style);

        return $this;
    }

    /** Colours, logo and company details used by the templates. */
    public function theme(array $theme): static
    {
        $this->theme = array_replace_recursive($this->theme, $theme);
        $this->prepared = null;

        return $this;
    }

    /** A font name from config "fonts": cairo, tajawal, naskh or one you registered. */
    public function font(string $font): static
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9 _-]{0,63}\z/', $font)) {
            throw new InvalidArgumentException('Invalid font name ['.substr($font, 0, 40).'].');
        }

        $this->font = strtolower($font);

        return $this;
    }

    /**
     * @param  string|array{0: float, 1: float}  $paper  "A4", "A4-L" (landscape) or [width, height] in mm
     */
    public function paper(string|array $paper, ?string $orientation = null): static
    {
        [$this->paper, $suffix] = self::splitPaper($paper);

        if ($orientation ??= $suffix) {
            $this->orientation = $orientation;
        }

        return $this;
    }

    /**
     * A paper name or size checked, and the orientation an "-L" or "-P" suffix asks for.
     *
     * @return array{0: string|array{0: float, 1: float}, 1: ?string}
     */
    private static function splitPaper(string|array $paper): array
    {
        if (is_array($paper)) {
            $size = array_values($paper);

            if (count($size) !== 2 || ! is_numeric($size[0]) || ! is_numeric($size[1]) || $size[0] <= 0 || $size[1] <= 0) {
                throw new InvalidArgumentException('A paper size must be [width, height] in mm, for example [100, 150].');
            }

            return [[(float) $size[0], (float) $size[1]], null];
        }

        $orientation = null;

        // "A4-L" as mPDF writes it: A4, landscape.
        if (preg_match('/^(.+)-([LP])$/i', $paper, $match)) {
            [$paper, $orientation] = [$match[1], strtoupper($match[2]) === 'L' ? 'landscape' : 'portrait'];
        }

        if (! isset(PdfOptions::PAPER_SIZES[strtoupper($paper)])) {
            throw PdfOptions::unknownPaper($paper);
        }

        return [$paper, $orientation];
    }

    public function landscape(): static
    {
        $this->orientation = 'landscape';

        return $this;
    }

    public function portrait(): static
    {
        $this->orientation = 'portrait';

        return $this;
    }

    /** Millimetres. One value for all sides, or top, right, bottom, left. */
    public function margins(float $top, ?float $right = null, ?float $bottom = null, ?float $left = null): static
    {
        $this->margins = [$top, $right ?? $top, $bottom ?? $top, $left ?? $right ?? $top];

        return $this;
    }

    /** Page header HTML; may use {page} and {pages}. */
    public function header(string $html): static
    {
        $this->header = $html;

        return $this;
    }

    /** Page footer HTML; may use {page} and {pages}. */
    public function footer(string $html): static
    {
        $this->footer = $html;

        return $this;
    }

    /** PDF engine for this document: "mpdf", "chromium" or "gotenberg". */
    public function driver(string $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Text across every page of the PDF, such as "مسودة" or "نسخة". Word
     * files are made without it.
     */
    public function watermark(string $text, float $opacity = 0.12, string $color = '#000000'): static
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('The watermark text is empty.');
        }

        $this->watermark = [
            'text' => mb_substr(trim($text), 0, 100),
            'opacity' => max(0.01, min(1.0, $opacity)),
            'color' => Color::css($color, '#000000'),
        ];

        return $this;
    }

    /**
     * Encrypt the PDF. $user is asked for to open it ('' opens without one);
     * $owner unlocks everything, and is random when left out, so the limits
     * in $allow hold. Word files cannot take a password.
     *
     * @param  list<string>  $allow  any of self::PERMISSIONS
     */
    public function password(string $user, ?string $owner = null, array $allow = ['print', 'print-highres', 'copy']): static
    {
        if ($unknown = array_diff($allow, self::PERMISSIONS)) {
            throw new InvalidArgumentException('Unknown PDF permission ['.implode(', ', $unknown).'], expected any of: '.implode(', ', self::PERMISSIONS).'.');
        }

        $this->protection = ['user' => $user, 'owner' => $owner, 'allow' => array_values(array_unique($allow))];

        return $this;
    }

    public function withoutValidation(): static
    {
        $this->validate = false;
        $this->prepared = null;

        return $this;
    }

    public function pdf(?string $filename = null): File
    {
        // A copy, so changes made to this document afterwards do not reach the file.
        $document = clone $this;

        return $document->pdfFile($filename ?? ($this->template?->name ?? 'document').'.pdf', $document->options());
    }

    /**
     * A Word (.docx) file, from the template's word.docx or word.php, or from
     * the blocks added with make().
     */
    public function word(?string $filename = null): File
    {
        $this->ensureWordSupported();

        $document = clone $this;
        // Invalid data is reported here, as ->pdf() does, not when the file is first read.
        $document->templateData();

        return $document->wordFile($filename ?? ($this->template?->name ?? 'document').'.docx');
    }

    /** The file ->pdf() returns; called on the document's copy. */
    protected function pdfFile(string $filename, PdfOptions $options): File
    {
        return File::pdf(fn () => $this->renderPdf($options), $filename);
    }

    /** The file ->word() returns; called on the document's copy. */
    protected function wordFile(string $filename): File
    {
        return File::word(fn () => $this->renderWord(), $filename);
    }

    /** @return array{0: string, 1: string} [bytes, engine name] */
    protected function renderPdf(PdfOptions $options): array
    {
        return $this->services->pdf->render('', $options, $this->driver, fn (PdfDriver $engine) => $this->toHtml($engine, $options));
    }

    /**
     * The final HTML handed to the engine; useful for previews and debugging.
     */
    public function toHtml(?PdfDriver $engine = null, ?PdfOptions $options = null): string
    {
        $options ??= $this->options();
        $engine ??= $this->services->pdf->driver($this->driver);
        $context = $this->context($options, $engine);
        $data = $this->viewData($context);

        $html = match (true) {
            $this->builder !== null => $this->wrapHtml((new HtmlRenderer)->render($this->builder, $context), $context),
            $this->template !== null && ! $this->template->hasPdfView() && $this->template->supportsPdf()
                => $this->wrapHtml((new HtmlRenderer)->render($this->runLayout($this->template->pdfLayout(), $data, $context), $context), $context),
            $this->template !== null => $this->services->views->file($this->template->pdfView(), $data),
            $this->view !== null => $this->services->views->view($this->view, $data),
            default => $this->wrapHtml((string) $this->html, $context),
        };

        $html = $this->withTitle($html, $options->title);

        if ($context->usesCssFonts() && ! str_contains($html, '@font-face')) {
            $html = preg_replace('/<\/head>/i', '<style>'.$context->fontCss.'</style></head>', $html, 1) ?? $html;
        }

        if ($context->usesCssFonts()) {
            $html = $this->withNamedFonts($html, $options->font);
        }

        return $context->numerals === Numerals::ARABIC
            ? Numerals::convertHtml($html, Numerals::ARABIC, $this->services->fonts->hasArabicSeparators($context->font))
            : $html;
    }

    public function options(): PdfOptions
    {
        $locale = $this->resolvedLocale();
        $direction = $this->direction ?? Direction::forLocale($locale);
        $data = $this->templateData();
        $numerals = $this->resolvedNumerals();

        // Headers and footers, given or from the template, in the document's digits.
        $digits = fn (?string $html) => $html !== null && $numerals === Numerals::ARABIC
            ? Numerals::convertHtml($html, Numerals::ARABIC, $this->services->fonts->hasArabicSeparators($this->resolvedFont($direction)))
            : $html;

        $partial = function (?string $view) use ($data, $locale, $direction) {
            if ($view === null) {
                return null;
            }

            $context = $this->context(new PdfOptions(locale: $locale, direction: $direction, font: $this->resolvedFont($direction)), null);

            return $this->services->views->file($view, ['doc' => $context] + $data);
        };

        // The template's paper wins over the config's, and its own orientation over a suffix like "-L".
        [$templatePaper, $templateSuffix] = self::splitPaper($this->template?->paper() ?? $this->config('pdf.paper') ?? 'A4');

        return new PdfOptions(
            paper: $this->paper ?? $templatePaper,
            orientation: $this->orientation ?? $this->template?->orientation() ?? $templateSuffix ?? $this->config('pdf.orientation') ?? 'portrait',
            margins: self::expandMargins($this->margins ?? $this->template?->margins() ?? $this->config('pdf.margins') ?? [15, 15, 15, 15]),
            direction: $direction,
            locale: $locale,
            font: $this->resolvedFont($direction),
            header: $digits($this->header ?? $partial($this->template?->headerView())),
            footer: $digits($this->footer ?? $partial($this->template?->footerView())),
            title: $this->title ?? $this->template?->title(),
            author: $this->resolvedTheme()['company']['name'] ?? null,
            numerals: $numerals,
            watermark: $this->watermark === null ? null : [
                'text' => $numerals === Numerals::ARABIC ? Numerals::convert($this->watermark['text'], Numerals::ARABIC) : $this->watermark['text'],
            ] + $this->watermark,
            protection: $this->protection,
        );
    }

    /** [10, 20] in a config or template.php means what ->margins(10, 20) means. */
    private static function expandMargins(array $margins): array
    {
        $margins = array_map('floatval', array_values($margins)) ?: [15.0];
        [$top, $right, $bottom, $left] = $margins + [null, null, null, null];

        return [$top, $right ?? $top, $bottom ?? $top, $left ?? $right ?? $top];
    }

    public function __clone()
    {
        if ($this->builder !== null) {
            $this->builder = clone $this->builder;
        }
    }

    /**
     * Add blocks to a make() document: heading, paragraph, table, image,
     * qr, spacer, pageBreak, line.
     */
    public function __call(string $method, array $arguments): static
    {
        if ($this->builder === null || ! is_callable([$this->builder, $method]) || in_array($method, ['blocks', 'isEmpty'], true)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
        }

        $this->builder->{$method}(...$arguments);

        return $this;
    }

    protected function ensureWordSupported(): void
    {
        if ($this->builder === null && ($this->template === null || ! $this->template->supportsWord())) {
            throw $this->template ? WordNotSupported::forTemplate($this->template->name) : WordNotSupported::forSource();
        }

        // A file the caller believes is locked must not go out open.
        if ($this->protection !== null) {
            throw new LogicException('Word files cannot take a password; ->password() works for PDF files only.');
        }
    }

    /** @return array{0: string, 1: string} [bytes, engine name] */
    protected function renderWord(): array
    {
        $options = $this->options();
        $context = $this->context($options, null);
        $data = $this->templateData();

        if ($this->builder === null && ($file = $this->template->wordFile())) {
            return [(new DocxTemplateFiller)->fill($file, $data, $context), 'docx-template'];
        }

        $builder = $this->builder ?? $this->runLayout($this->template->wordLayout(), $data, $context);
        $renderer = new WordRenderer((array) $this->config('word', []));

        return [$renderer->render($builder, $context, $options), 'phpword'];
    }

    /** Run a template's layout.php or word.php. */
    private function runLayout(callable $layout, array $data, DocContext $context): DocumentBuilder
    {
        $builder = new DocumentBuilder;
        $layout($builder, $data, $context);

        return $builder;
    }

    private function context(PdfOptions $options, ?PdfDriver $engine): DocContext
    {
        $usesCss = $engine?->usesCssFonts() ?? false;

        return new DocContext(
            locale: $options->locale,
            direction: $options->direction,
            font: $options->font,
            theme: $this->resolvedTheme(),
            numerals: $this->resolvedNumerals(),
            engine: $engine ? $engine::class : '',
            fontCss: $usesCss ? $this->services->fonts->cssFontFaces([$options->font]) : '',
            translations: $this->template?->translations($options->locale) ?? [],
            fallbackTranslations: $this->template?->translations('en') ?? [],
            imagePaths: $this->config('images.paths'),
            remoteImages: $this->remoteImages(),
            timezone: $this->services->timezone(),
        );
    }

    /** Config "images.remote": true, false, or hosts as a list or a comma-separated string. */
    private function remoteImages(): bool|array
    {
        $remote = $this->config('images.remote', false);

        if (is_array($remote) || is_bool($remote) || $remote === null) {
            return is_array($remote) ? $remote : (bool) $remote;
        }

        return filter_var($remote, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? array_values(array_filter(array_map('trim', explode(',', (string) $remote))));
    }

    private function viewData(DocContext $context): array
    {
        // $doc is always the context; a data key named "doc" does not replace it.
        return ['doc' => $context] + $this->templateData();
    }

    protected function templateData(): array
    {
        if ($this->template === null) {
            return $this->data;
        }

        if ($this->prepared !== null) {
            return $this->prepared;
        }

        $data = array_replace_recursive($this->template->defaults(), $this->toArrays($this->data));

        if ($this->validate && $this->template->rules() !== []) {
            $this->services->validator->validate($data, $this->template->rules());
        }

        return $this->prepared = $this->prepareData($data);
    }

    /** Run the template's "prepare"; it may throw ValidationFailed for data the rules cannot check. */
    protected function prepareData(array $data): array
    {
        return $this->template->prepare($data, $this->resolvedTheme());
    }

    /** Template data as plain arrays, so rules like "array" pass; Laravel also turns collections and models into arrays. */
    protected function toArrays(array $data): array
    {
        return array_map(fn ($value) => is_array($value) ? $this->toArrays($value) : $value, $data);
    }

    /**
     * The PDF's title, which every engine reads from <title>: ->title() replaces
     * the page's own title, the template's title fills an empty one.
     */
    private function withTitle(string $html, ?string $title): string
    {
        if ($title === null || $title === '') {
            return $html;
        }

        $tag = '<title>'.Html::escape($title).'</title>';

        if (! preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $match)) {
            return preg_replace('/<\/head>/i', $tag.'</head>', $html, 1) ?? $html;
        }

        return $this->title !== null || trim($match[1]) === ''
            ? (preg_replace('/<title\b[^>]*>.*?<\/title>/is', addcslashes($tag, '\\$'), $html, 1) ?? $html)
            : $html;
    }

    /**
     * Chrome only knows the fonts it is given: a registered font named in the
     * page's CSS (font-family: 'naskh') gets its @font-face, as mPDF would find it.
     */
    private function withNamedFonts(string $html, string $documentFont): string
    {
        $named = array_filter(array_keys($this->services->fonts->all()), fn (string $name) => $name !== strtolower($documentFont)
            && ! str_contains($html, "@font-face{font-family:'{$name}'")
            && preg_match('/font-family\s*:[^;}<>]*?\b'.preg_quote($name, '/').'\b/i', $html) === 1);

        if ($named === []) {
            return $html;
        }

        return preg_replace('/<\/head>/i', '<style>'.addcslashes($this->services->fonts->cssFontFaces($named), '\\$').'</style></head>', $html, 1) ?? $html;
    }

    /** A body fragment gets the package's page around it; a whole page stays as it is. */
    private function wrapHtml(string $html, DocContext $context): string
    {
        return preg_match('/<html[\s>]/i', $html) ? $html : PageLayout::render($context, $html);
    }

    /** A setting by "dot.notation" key, as in config/easy-pdf-word.php. */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->services->config($key, $default);
    }

    protected function resolvedLocale(): string
    {
        return $this->locale ?? $this->config('locale') ?? $this->services->appLocale();
    }

    protected function resolvedNumerals(): string
    {
        return $this->numerals ?? Numerals::normalizeStyle($this->config('numerals', 'latin'));
    }

    private function resolvedFont(string $direction): string
    {
        $key = $direction === 'rtl' ? 'default' : 'default_ltr';

        return $this->font ?? strtolower($this->config("fonts.{$key}", 'cairo'));
    }

    private function resolvedTheme(): array
    {
        $theme = array_replace_recursive(
            (array) $this->config('theme', []),
            $this->template?->theme() ?? [],
            $this->theme,
        );

        // Theme colours go into the templates' CSS, so only real colours pass.
        foreach (self::THEME_COLORS as $key => $default) {
            $theme[$key] = Color::css($theme[$key] ?? null, $default);
        }

        return $theme;
    }
}
