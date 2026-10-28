# Changelog

All notable changes to `biztecheg/easy-pdf-word` are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Support\HtmlSanitizer` cleans HTML from a rich-text editor (TipTap, CKEditor) for documents: it keeps text, headings, lists, quotes, links, tables and images with a short list of styles (colours, alignment, direction, bold, italic, underline and strike-through, table widths), and removes scripts, event attributes, other styles and links that are not http, https or mailto. Its output is well-formed XHTML in one shape for the PDF and Word renderers. Input is limited to 256 KB, 20 levels of nesting and tables of 200 rows and 20 columns, and the cuts are reported through `warn`.
- `DocumentBuilder::row($cells, $options)` builds a table row with options: `style` (bold, italic, size, color, background, align, border for every cell; a cell's own style wins), `total` (bold, with a line above it; several rows can be totals) and `keep_with_next` (the row stays on one page with the next one, in PDF and Word). Rows that are not `row()` objects, including rows built from records, are read as before.
- The `richText($html, ['font_size' => 10])` builder block adds HTML from a rich-text editor, cleaned by `HtmlSanitizer`, to a document built in code. In PDF it keeps its formatting in the document's direction and numerals, with the builder's heading sizes (`h4` included) and tables with borders around every cell. Its images go through the document's image rules, and only data URIs and URLs are accepted: users cannot point at files on the server. Word files get its text as plain paragraphs until rich text in Word arrives.
- `DocContext::warn()` sends a message to the document's `warn` handler; `PdfOptions::contentWidth()` gives the width of the page's text in mm.

### Changed

- mPDF 8.2.4 is the lowest supported version (`conflict` with `<8.2.4`): it is the first to keep a table row with the next one.

## [1.2.0] - 2026-10-13

### Added

- Turkish: the twelve bundled templates in Turkish (`->locale('tr')`), with Turkish dates (08.10.2026), numbers (1.250,50), month and day names, and amounts in words ("Yalnız bin iki yüz elli Türk Lirası ve 50/100").
- Language files in `resources/lang/{language}.json` (month and day names, date and number formats, list separator, currency labels and the wording of amounts in words), read by `Support\Language`. `Language::addPath()` adds a folder with more languages or keys that replace the bundled ones.
- Template labels can be `lang/{locale}.json` files; a `lang/{locale}.php` file next to one wins over its keys.
- `$doc->date()` writes a date in the language's format, `$doc->language()` reads a key of the language file, `$doc->join()` joins values with the language's list separator and `$doc->ucfirst()` capitalises any first letter (Turkish "i" as "İ").

### Changed

- The bundled templates' labels moved from `lang/*.php` to `lang/*.json`. Copies of the templates with `.php` labels keep working.
- `number()`, `numberText()` and `rate()` use the language's decimal and thousands separators; Arabic and English are unchanged.
- Currency labels (ج.م, ر.س) follow the document's language instead of its direction, so a Persian or Hebrew document no longer gets the Arabic labels.

### Fixed

- Lists in Word placeholders were joined with the Arabic comma in every language; they use the language's separator.
- Contract labels capitalised with `ucfirst()` left a non-ASCII first letter small.

## [1.1.0] - 2026-10-13

### Security

- An SVG image inside a data URI that claimed another type (`data:image/png;base64,...`) skipped the SVG check, and mPDF then fetched the URLs and read the local images it pointed to, outside `images.paths` and `images.remote`. Data URIs are now checked by their content and rebuilt with their real type.
- mPDF downloaded images from allowed hosts itself, so an SVG uploaded to such a host reached it unchecked. Allowed image URLs are now fetched by the library for every engine (no redirects, 10 seconds, 10 MB) and checked like local files. Word image downloads got the same 10 MB limit.

### Added

- The `warn` callback (`error_log()` by default) is told when an image is left out because its folder is not in `images.paths` or its host is not in `images.remote`.

### Changed

- The template validator matches Laravel in four more cases: `required_if` does not apply when the other field is missing, `in` no longer accepts `false` for `0`, `date_format` accepts any of the formats it lists, and `after_or_equal` refuses values that are not dates. The `required_if` and `gt` messages are worded as Laravel words them.
- Arabic locales with a region (`ar_SY`, `ar_IQ`, `ar_LB`) use their own month names with ext-intl: أيلول instead of سبتمبر, as the Laravel package did before 1.4.
- Error messages name the plain PHP setting (`pdf.drivers.gotenberg.url`) or method (`extend()`, `make()`), with the Laravel form after it.
- Composer refuses to install this package next to `biztecheg/laravel-easy-pdf-word` older than 1.4, which still held the same classes.

### Fixed

- `send()` dropped the end of the file when something had been printed before it into an output buffer (php-fpm's `output_buffering`): the stray output went first and `Content-Length` cut the file short. Buffered output is now discarded before the file is sent.
- `->word()` without the zip extension failed only when the file was read, with `Class "ZipArchive" not found`; it now says at once that Word files need ext-zip.
- The English receipt without ext-intl showed an empty "The sum of" row; the row is left out.
- Control characters in a file name (a NUL byte in a ZIP entry name) are removed, and the Latin fallback download name writes one `_` per letter instead of one per byte when neither intl nor iconv is installed.

## [1.0.1] - 2026-10-11

### Fixed

- Files sent to the browser with an Arabic name got a fallback name of question marks (`??????-1024.pdf`) for old clients. With ext-intl the fallback is now written in Latin letters (`fatwrt-1024.pdf`), as in the Laravel package; without it, the unknown letters become `_`.

## [1.0.0] - 2026-10-11

### Added

- First release of the framework-free core of [biztecheg/laravel-easy-pdf-word](https://github.com/BiztechEG/laravel-easy-pdf-word), split out with its git history. Everything that does not need Laravel lives here, under the same `BiztechEG\EasyPdfWord\` namespace, so code written for the Laravel package keeps working.
- `EasyPdfWord::create($config)` makes documents in plain PHP: the twelve bundled templates, your own templates and plain PHP views, HTML, and documents built in code, as PDF and Word files that can be saved, sent to the browser or zipped.
- The Arabic helpers (tafqeet, Hijri dates, numerals, direction), the ZATCA QR, the bundled fonts, the mPDF, Browsershot and Gotenberg engines with fallback, watermarks and passwords, and a template data validator with Laravel's rules and messages.
