<?php

namespace BiztechEG\EasyPdfWord\Fonts;

use InvalidArgumentException;

/**
 * Fonts known to the package: the bundled Arabic fonts plus the ones an app
 * registers in config("easy-pdf-word.fonts.custom") or with register().
 */
class FontRegistry
{
    /** @var array<string, array{regular: string, bold?: string, italic?: string, bold_italic?: string, arabic?: bool}> */
    private array $fonts = [];

    public function __construct(array $custom = [])
    {
        $dir = dirname(__DIR__, 2).'/resources/fonts';

        foreach (['cairo' => 'Cairo', 'tajawal' => 'Tajawal', 'naskh' => 'NotoNaskhArabic'] as $name => $file) {
            $this->register($name, [
                'regular' => "{$dir}/{$file}-Regular.ttf",
                'bold' => "{$dir}/{$file}-Bold.ttf",
                'arabic' => true,
            ]);
        }

        foreach ($custom as $name => $files) {
            $this->register($name, $files);
        }
    }

    public function register(string $name, array $files): static
    {
        if (empty($files['regular'])) {
            throw new InvalidArgumentException("Font [{$name}] needs at least a \"regular\" file.");
        }

        $this->fonts[strtolower($name)] = $files + ['arabic' => true];

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->fonts[strtolower($name)]);
    }

    public function get(string $name): array
    {
        return $this->fonts[strtolower($name)]
            ?? throw new InvalidArgumentException("Font [{$name}] is not registered.");
    }

    public function supportsArabic(string $name): bool
    {
        return $this->has($name) && (bool) $this->get($name)['arabic'];
    }

    /** @return array<string, array> */
    public function all(): array
    {
        return $this->fonts;
    }

    /**
     * Font definitions in mPDF's "fontdata" shape. Each font gets its own
     * directory entry because mPDF looks fonts up by file name.
     *
     * @return array{0: string[], 1: array<string, array>} font directories, fontdata
     */
    public function forMpdf(int $kashida = 75): array
    {
        $dirs = [];
        $fontdata = [];
        $styles = ['regular' => 'R', 'bold' => 'B', 'italic' => 'I', 'bold_italic' => 'BI'];

        foreach ($this->fonts as $name => $files) {
            $entry = ['useOTL' => 0xFF, 'useKashida' => $kashida];

            foreach ($styles as $style => $key) {
                if (! empty($files[$style])) {
                    $dirs[] = dirname($files[$style]);
                    $entry[$key] = basename($files[$style]);
                }
            }

            $fontdata[$name] = $entry;
        }

        return [array_values(array_unique($dirs)), $fontdata];
    }

    /**
     * @font-face rules with the font files embedded as data URIs, for
     * engines that load the HTML without file access (Chromium, Gotenberg).
     */
    public function cssFontFaces(array $names): string
    {
        $css = '';
        $styles = [
            'regular' => ['normal', 'normal'],
            'bold' => ['bold', 'normal'],
            'italic' => ['normal', 'italic'],
            'bold_italic' => ['bold', 'italic'],
        ];

        foreach (array_unique(array_map('strtolower', $names)) as $name) {
            if (! $this->has($name)) {
                continue;
            }

            foreach ($styles as $style => [$weight, $fontStyle]) {
                $file = $this->get($name)[$style] ?? null;

                if (! $file || ! is_file($file)) {
                    continue;
                }

                $data = base64_encode(file_get_contents($file));
                $css .= "@font-face{font-family:'{$name}';font-weight:{$weight};font-style:{$fontStyle};"
                    ."src:url(data:font/ttf;base64,{$data}) format('truetype');}\n";
            }
        }

        return $css;
    }
}
