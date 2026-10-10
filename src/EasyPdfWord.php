<?php

namespace BiztechEG\EasyPdfWord;

use BiztechEG\EasyPdfWord\Arabic\Tafqeet;
use BiztechEG\EasyPdfWord\Contracts\HttpClient;
use BiztechEG\EasyPdfWord\Contracts\Validator;
use BiztechEG\EasyPdfWord\Contracts\ViewRenderer;
use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Pdf\PdfManager;
use BiztechEG\EasyPdfWord\Templates\TemplateRegistry;
use BiztechEG\EasyPdfWord\Validation\RuleValidator;
use BiztechEG\EasyPdfWord\View\PhpViewRenderer;
use Closure;

/**
 * The library without a framework:
 *
 *   $docs = EasyPdfWord::create(['locale' => 'ar', 'theme' => ['company' => ['name' => 'شركة النور']]]);
 *   $docs->template('invoice', $data)->pdf()->save(__DIR__.'/invoice.pdf');
 *
 * The settings are those of config/easy-pdf-word.php; leave out what you do
 * not change.
 */
final class EasyPdfWord
{
    /**
     * @param  array  $config  merged over defaults(); lists such as margins or paths replace the default list
     * @param  array{http?: HttpClient, validator?: Validator, views?: ViewRenderer, warn?: Closure(string): void}  $services  replacements for the built-in ones
     */
    public static function create(array $config = [], array $services = []): DocumentFactory
    {
        $config = self::merge(self::defaults(), $config);

        foreach ((array) $config['currencies'] as $code => $definition) {
            Tafqeet::registerCurrency($code, $definition);
        }

        $fonts = new FontRegistry((array) $config['fonts']['custom']);
        $pdf = new PdfManager($config, $fonts, $services['http'] ?? null, $services['warn'] ?? null);

        return new DocumentFactory(
            new DocumentServices(
                pdf: $pdf,
                fonts: $fonts,
                views: $services['views'] ?? new PhpViewRenderer((array) $config['views']['paths']),
                validator: $services['validator'] ?? new RuleValidator,
                config: $config,
            ),
            new TemplateRegistry((array) $config['templates']['paths']),
        );
    }

    /**
     * The settings create() starts from: config/easy-pdf-word.php without
     * Laravel's .env values and folders.
     */
    public static function defaults(): array
    {
        return [
            // null means "en" here; in Laravel, the app locale.
            'locale' => null,
            'numerals' => 'latin',
            'pdf' => [
                'driver' => 'mpdf',
                'fallback' => 'mpdf',
                'paper' => 'A4',
                'orientation' => 'portrait',
                'margins' => [15, 15, 15, 15],
                'drivers' => [
                    'mpdf' => [
                        'temp_dir' => null,
                        'use_kashida' => 75,
                        'auto_lang_to_font' => false,
                    ],
                    'browsershot' => [
                        'node_binary' => null,
                        'npm_binary' => null,
                        'node_modules_path' => null,
                        'chrome_path' => null,
                        'no_sandbox' => false,
                        'javascript' => false,
                        'timeout' => 60,
                    ],
                    'gotenberg' => [
                        'url' => 'http://localhost:3000',
                        'javascript' => false,
                        'timeout' => 60,
                    ],
                ],
            ],
            'fonts' => [
                'default' => 'cairo',
                'default_ltr' => 'cairo',
                'custom' => [],
            ],
            'images' => [
                // Folders local images (logo, signature, stamp) may come from; [] allows none, null any folder.
                'paths' => [],
                'remote' => false,
            ],
            'word' => [
                'font' => 'Arial',
                'font_size' => 11,
            ],
            'templates' => [
                // Your template folders, searched before the bundled templates.
                'paths' => [],
            ],
            'views' => [
                // Folders where ->view('invoices.show') finds invoices/show.html.php.
                'paths' => [],
            ],
            'theme' => [
                'primary' => '#0F766E',
                'text' => '#1F2937',
                'muted' => '#6B7280',
                'border' => '#E5E7EB',
                'logo' => null,
                'company' => [
                    'name' => null,
                    'address' => null,
                    'phone' => null,
                    'email' => null,
                    'tax_number' => null,
                ],
            ],
            'currencies' => [],
        ];
    }

    /** Nested settings merge key by key; a list (margins, folders) replaces the default list whole. */
    private static function merge(array $defaults, array $config): array
    {
        foreach ($config as $key => $value) {
            $defaults[$key] = is_array($value) && ! array_is_list($value) && is_array($defaults[$key] ?? null)
                ? self::merge($defaults[$key], $value)
                : $value;
        }

        return $defaults;
    }
}
