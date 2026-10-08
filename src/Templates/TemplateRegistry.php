<?php

namespace BiztechEG\EasyPdfWord\Templates;

use BiztechEG\EasyPdfWord\Exceptions\TemplateNotFound;

/**
 * Finds templates by name. Project folders are searched before the
 * package's own templates, so a copied template overrides the original.
 */
class TemplateRegistry
{
    /** @var string[] */
    private array $paths;

    public function __construct(array $paths = [])
    {
        $this->paths = array_values(array_unique([...$paths, self::packagePath()]));
    }

    public static function packagePath(): string
    {
        return dirname(__DIR__, 2).'/resources/templates';
    }

    public function addPath(string $path, bool $first = true): static
    {
        $this->paths = array_values(array_unique($first ? [$path, ...$this->paths] : [...$this->paths, $path]));

        return $this;
    }

    /** @return string[] */
    public function paths(): array
    {
        return $this->paths;
    }

    public function exists(string $name): bool
    {
        return $this->locate($name) !== null;
    }

    public function get(string $name): Template
    {
        $path = $this->locate($name) ?? throw TemplateNotFound::named($name, $this->paths);

        return new Template($name, $path);
    }

    /** @return array<string, Template> name => template, project ones first */
    public function all(): array
    {
        $templates = [];

        foreach ($this->paths as $base) {
            foreach (glob($base.'/*/template.php') ?: [] as $manifest) {
                $name = basename(dirname($manifest));
                $templates[$name] ??= new Template($name, dirname($manifest));
            }
        }

        return $templates;
    }

    public function isBundled(string $name): bool
    {
        return $this->locate($name) === self::packagePath().'/'.$name;
    }

    private function locate(string $name): ?string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            return null;
        }

        foreach ($this->paths as $base) {
            foreach (['template.php', 'pdf.blade.php', 'word.php', 'word.docx'] as $file) {
                if (is_file("{$base}/{$name}/{$file}")) {
                    return "{$base}/{$name}";
                }
            }
        }

        return null;
    }
}
