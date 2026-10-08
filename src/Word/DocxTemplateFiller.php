<?php

namespace BiztechEG\EasyPdfWord\Word;

use BiztechEG\EasyPdfWord\Arabic\Numerals;
use BiztechEG\EasyPdfWord\Exceptions\DriverNotAvailable;
use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Support\Qr;
use DateTimeInterface;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use Stringable;

/**
 * Fills a Word file designed in Word itself (word.docx in a template folder).
 *
 * Placeholders use dots for nested data:
 *
 *   ${invoice.number}  ${buyer.name}  ${totals.total}
 *
 * A list of rows fills a table: put ${items.description}, ${items.quantity}
 * ... in one table row and the row is repeated for every item. ${items.row_number}
 * is the row number.
 *
 * Images: ${logo}, ${theme.logo} or any placeholder whose value is an image
 * file or data URI; the size can be given in the placeholder,
 * ${logo:40:20} (width:height in pixels).
 *
 * Also available: ${doc.hijri_date} (from a "date" value), ${doc.qr} (a QR
 * image of the "qr" value), ${doc.today}, ${theme.*}
 * (company name, address ...) and every label in lang/{locale}.php as ${t.key}.
 */
class DocxTemplateFiller
{
    /** @var string[] temporary files, removed after each fill */
    private array $temporary = [];

    public function fill(string $path, array $data, DocContext $doc): string
    {
        if (! class_exists(TemplateProcessor::class)) {
            throw DriverNotAvailable::missingPackage('word', 'phpoffice/phpword');
        }

        $escaping = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);

        try {
            $processor = new TemplateProcessor($path);
            $values = $this->flatten($data + ['theme' => $doc->theme, 't' => $doc->translations()]);
            $values += $this->extras($data, $doc);
            $variables = $processor->getVariables();

            $this->fillRows($processor, $data, $variables, $doc);

            foreach (array_unique($processor->getVariables()) as $variable) {
                $name = explode(':', $variable)[0];
                $value = $values[$name] ?? null;

                if ($this->isImage($value) && ($image = $this->imageFile($value, $doc))) {
                    $processor->setImageValue($name, $this->imageOptions($variable, $image));

                    continue;
                }

                $processor->setValue($variable, $this->text($value, $doc));
            }

            $file = $this->temporary[] = tempnam(sys_get_temp_dir(), 'easy-docx');
            $processor->saveAs($file);
            $content = (string) file_get_contents($file);

            return $content;
        } finally {
            Settings::setOutputEscapingEnabled($escaping);
            $this->cleanup();
        }
    }

    /**
     * Repeat a table row for each item of a list, e.g. ${items.description}.
     */
    private function fillRows(TemplateProcessor $processor, array $data, array $variables, DocContext $doc): void
    {
        foreach ($data as $key => $list) {
            if (! is_array($list) || ! array_is_list($list) || $list === [] || ! is_array($list[0])) {
                continue;
            }

            $first = collect($variables)->first(fn ($v) => str_starts_with($v, $key.'.'));

            if ($first === null) {
                continue;
            }

            $rows = [];

            foreach ($list as $i => $item) {
                $row = [$key.'.row_number' => $this->text($i + 1, $doc)];

                foreach ($this->flatten((array) $item, $key.'.') as $name => $value) {
                    $row[$name] = $this->text($value, $doc);
                }

                foreach ($variables as $variable) {
                    if (str_starts_with($variable, $key.'.')) {
                        $row[$variable] ??= '';
                    }
                }

                $rows[] = $row;
            }

            try {
                $processor->cloneRowAndSetValues($first, $rows);
            } catch (\Throwable) {
                // The placeholder is not in a table row; fill it like any other value.
            }
        }
    }

    private function extras(array $data, DocContext $doc): array
    {
        $date = $data['date'] ?? $data['invoice']['date'] ?? null;

        return array_filter([
            'doc.hijri_date' => $date && $doc->hasHijri() ? $doc->hijri($date) : null,
            'doc.today' => now()->format('Y/m/d'),
            'doc.qr' => ! empty($data['qr']) && is_string($data['qr']) ? Qr::dataUri($data['qr']) : null,
        ], fn ($value) => $value !== null);
    }

    /** @return array<string, mixed> */
    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            if (is_array($value) && ! array_is_list($value)) {
                $flat += $this->flatten($value, $prefix.$key.'.');
            } elseif (is_array($value)) {
                if ($value === [] || ! is_array($value[0] ?? null)) {
                    $flat[$prefix.$key] = implode('، ', array_map('strval', $value));
                }
            } else {
                $flat[$prefix.$key] = $value;
            }
        }

        return $flat;
    }

    private function text(mixed $value, DocContext $doc): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? '✓' : '',
            is_float($value) => $doc->numberText($value),
            $value instanceof DateTimeInterface => $value->format('Y/m/d'),
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => '',
        };

        return $doc->numerals === Numerals::ARABIC ? Numerals::toArabic($text) : $text;
    }

    private function isImage(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        return str_starts_with($value, 'data:image/')
            || (preg_match('/\.(png|jpe?g|gif|bmp)$/i', $value) === 1 && is_file($value));
    }

    /** A local copy of the image, read through $doc->image() so the allowed folders apply. */
    private function imageFile(string $value, DocContext $doc): ?string
    {
        $value = str_starts_with($value, 'data:') ? $value : $doc->image($value);

        if ($value === null) {
            return null;
        }

        $file = tempnam(sys_get_temp_dir(), 'easy-img');
        file_put_contents($file, base64_decode(substr($value, strpos($value, ',') + 1)));
        $this->temporary[] = $file;

        return $file;
    }

    private function imageOptions(string $variable, string $file): array
    {
        $parts = explode(':', $variable);

        return array_filter([
            'path' => $file,
            'width' => $parts[1] ?? 120,
            'height' => $parts[2] ?? '',
            'ratio' => true,
        ], fn ($value) => $value !== '');
    }

    private function cleanup(): void
    {
        foreach ($this->temporary as $file) {
            @unlink($file);
        }

        $this->temporary = [];
    }
}
