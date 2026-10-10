# Changelog

All notable changes to `biztecheg/easy-pdf-word` are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- First release of the framework-free core of [biztecheg/laravel-easy-pdf-word](https://github.com/BiztechEG/laravel-easy-pdf-word), split out with its git history. Everything that does not need Laravel lives here, under the same `BiztechEG\EasyPdfWord\` namespace, so code written for the Laravel package keeps working.
- `EasyPdfWord::create($config)` makes documents in plain PHP: the twelve bundled templates, your own templates and plain PHP views, HTML, and documents built in code, as PDF and Word files that can be saved, sent to the browser or zipped.
- The Arabic helpers (tafqeet, Hijri dates, numerals, direction), the ZATCA QR, the bundled fonts, the mPDF, Browsershot and Gotenberg engines with fallback, watermarks and passwords, and a template data validator with Laravel's rules and messages.
