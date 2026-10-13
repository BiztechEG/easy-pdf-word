# Changelog

All notable changes to `biztecheg/easy-pdf-word` are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
