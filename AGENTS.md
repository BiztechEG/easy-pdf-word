# AGENTS.md — biztecheg/easy-pdf-word (core)

Guide for coding agents working in this repository. Fuller project history and
the roadmap live with the maintainer (BizTech); the rules below are the ones
that matter when changing code here.

## What this repo is

The framework-agnostic PHP core of Easy PDF & Word: generate PDF and Word files
in any language with first-class Arabic (RTL, shaping, Arabic-Indic numerals,
tafqeet, Hijri dates) and 12 ready-made templates. PHP 8.2+, no framework.

- Packagist: `biztecheg/easy-pdf-word` (latest v1.2.0). License MIT.
- The Laravel package [BiztechEG/laravel-easy-pdf-word](https://github.com/BiztechEG/laravel-easy-pdf-word)
  (`biztecheg/laravel-easy-pdf-word`) depends on this repo from Packagist and
  shares the namespace `BiztechEG\EasyPdfWord\`. Template, engine, Arabic and
  language changes land here first, get released, then the Laravel package
  bumps its constraint.
- Docs site (built from the Laravel repo's `docs/`): https://easypdf.biztech-eg.com

## Layout

- `src/EasyPdfWord.php` — `EasyPdfWord::create()` entry point for plain PHP.
- `src/Document*.php` — document builder/factory/services.
- `src/Pdf/` — `PdfManager` and drivers (`MpdfDriver` default, `BrowsershotDriver`,
  `GotenbergDriver`), watermark, password (`PdfProtector`).
- `src/Word/` — PhpWord renderer and `word.docx` placeholder filler.
- `src/Arabic/` — direction, numerals, tafqeet, Hijri. `src/Zatca/` — ZATCA QR.
- `src/Support/` — `DocContext` (incl. `image()`), `Color`, `Locale`, `Language`,
  `Currency`, `Dates`, `Html`, `HtmlString`, HTTP client.
- `src/Contracts/` — `ViewRenderer`, `Validator`, `HttpClient`, `PdfDriver`
  (the Laravel package provides Laravel adapters for these).
- `resources/templates/<name>/` — `template.php`, `pdf.html.php` (or shared
  `layout.php`), `footer.html.php`, `word.php`, `lang/{ar,en,tr}.json`.
- `resources/lang/{ar,en,tr}.json` — locale data (months, days, date format,
  separators, currencies, amount-in-words patterns), read by `Support\Language`.
  Kept as data files on purpose so ports to other programming languages can reuse them.
- `resources/fonts/` — Cairo, Noto Naskh Arabic, Tajawal (OFL).
- `bin/mpdf-font-fix.py` — patches fonts mPDF would reject (GDEF MarkGlyphSets, GSUB 5.3).

## Running tests

```bash
composer install          # in a cloud container use --prefer-source if zip downloads fail
vendor/bin/phpunit
```

CI (`.github/workflows/tests.yml`) runs PHP 8.2–8.5 plus a prefer-lowest job,
`composer validate --strict`, `composer audit`, and fails if any `laravel/*` or
`illuminate/*` package is installed. Keep the core free of Laravel and Carbon
(`nesbot/carbon` is dev-only); `tests/Unit/CoreBoundaryTest.php` guards this.

When changing rendering, compare output (HTML and Word XML for all 12 templates
× ar/en/tr) against `main`; the language and core-split refactors were verified
byte-identical this way.

## Rules from the maintainer

- **Commits are authored as BizTech**: before committing run
  `git config user.name "BizTech"` and
  `git config user.email "28879302+amrsoft13@users.noreply.github.com"`.
  No `Co-Authored-By: Claude` / `Claude-Session` trailers in commit messages.
  Amending must keep BizTech as committer too.
- **Many small commits**, one per logical change; never one big commit.
- **Branch every PR from `main`**; no stacked PRs (they get merged into their
  base as-is).
- **Review after each phase**: finish a phase, review and fix it, then start the next.
- **Releases**: agents can't create tags/releases. Open a PR that dates the
  `CHANGELOG.md` entry, write release notes for BizTech, who publishes the
  GitHub release (Packagist updates itself). Semver: new features → minor.
- Repo settings (description, topics) are changed by BizTech, not agents.

## Security defaults (keep them)

Template data often comes from users or tenants, so treat it as untrusted.

- Images only through `DocContext::image()`. Only http(s)/data schemes, no UNC
  paths, remote images off by default, SVGs with external href/url()/`<image>`/
  entities refused (mPDF follows them).
- `->locale()` accepts locale names only; template lang files load for
  `[a-z]{2,3}` only. `->font()` accepts plain names. Colours go through `Support\Color`.
- Escape output; `word.docx` filler breaks `${` so values can't create placeholders.
- Chromium JavaScript is off by default.
- Add a test for any new input path.

## Gotchas

- mPDF: Amiri font fails (use Noto Naskh Arabic); LRM shows as a box, use
  `<bdo dir="ltr">`; `text-align` on blocks inside `td` is ignored; Arabic digits
  next to Latin letters reorder codes, so wrap ids in ltr runs.
- Word: ltr runs need LRO (U+202D), not LRE; PhpWord language is `'ar-SA'`
  (no constant); footer page fields need a named paragraph style.
- Cairo draws `٫ ٬` like commas, so Arabic separators are only used for fonts
  flagged `arabic_separators` (Naskh).
- ICU rejects the `Z` time zone; pass `'GMT'.$date->format('P')`.
- Tajawal lacks Turkish ğ İ ş; no bundled font has ₺, so TRY prints "TL".
- Arabic wording: avoid verbs that must agree with an unknown issuer's gender
  (use passive, e.g. «تُمنح هذه الشهادة»); agree with a known recipient via a gender field.
- Deliberately unchanged: currencies are process-wide (static Tafqeet); mPDF uses
  ~85 KB memory per table row (documented).
- `composer install --prefer-source` can rewrite this clone's git remotes; check
  `git remote -v` before pushing.
