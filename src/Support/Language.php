<?php

namespace BiztechEG\EasyPdfWord\Support;

/**
 * What the library knows about a language (month and day names, date and
 * number formats, the wording of amounts in words), read from
 * resources/lang/{language}.json. The files are plain JSON so versions of
 * the library in other programming languages can read the same files.
 *
 * A language without a file, or a key its file leaves out, uses English.
 * addPath() adds a folder of more files: a new language, or keys that
 * replace the bundled ones.
 */
final class Language
{
    /** @var list<string> */
    private static array $paths = [];

    /** @var array<string, array> */
    private static array $files = [];

    /** @var array<string, array> */
    private static array $data = [];

    /** Folders searched after the bundled one, the last added winning. */
    public static function addPath(string $path): void
    {
        $path = rtrim($path, '/\\');

        if (! in_array($path, self::$paths, true)) {
            self::$paths[] = $path;
            self::$files = self::$data = [];
        }
    }

    /** Back to the bundled languages only. */
    public static function flush(): void
    {
        self::$paths = self::$files = self::$data = [];
    }

    /** True when a file describes the language ("tr" for "tr_TR"). */
    public static function has(string $locale): bool
    {
        $language = Locale::language($locale);

        return $language !== null && self::file($language) !== [];
    }

    /** All keys for the locale's language, English filling the gaps. */
    public static function data(string $locale): array
    {
        $language = Locale::language($locale) ?? 'en';

        return self::$data[$language] ??= array_replace_recursive(self::file('en'), self::file($language));
    }

    /** One key, e.g. "months" or "amount_in_words.only". */
    public static function get(string $locale, string $key, mixed $default = null): mixed
    {
        return Data::get(self::data($locale), $key, $default);
    }

    /** The languages that have a file: ["ar", "en", "tr"]. */
    public static function available(): array
    {
        $languages = [];

        foreach (self::folders() as $folder) {
            foreach (glob($folder.'/*.json') ?: [] as $file) {
                $language = basename($file, '.json');

                if (Locale::isValid($language)) {
                    $languages[] = strtolower($language);
                }
            }
        }

        $languages = array_values(array_unique($languages));
        sort($languages);

        return $languages;
    }

    /** One language's own keys, from the bundled file and every added folder. */
    private static function file(string $language): array
    {
        if (! array_key_exists($language, self::$files)) {
            $keys = [];

            foreach (self::folders() as $folder) {
                $file = $folder.'/'.$language.'.json';

                if (is_file($file)) {
                    $decoded = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
                    $keys = array_replace_recursive($keys, is_array($decoded) ? $decoded : []);
                }
            }

            self::$files[$language] = $keys;
        }

        return self::$files[$language];
    }

    /** @return list<string> */
    private static function folders(): array
    {
        return [dirname(__DIR__, 2).'/resources/lang', ...self::$paths];
    }
}
