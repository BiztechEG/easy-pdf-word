# Easy PDF & Word

[![Tests](https://github.com/BiztechEG/easy-pdf-word/actions/workflows/tests.yml/badge.svg)](https://github.com/BiztechEG/easy-pdf-word/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/biztecheg/easy-pdf-word)](https://packagist.org/packages/biztecheg/easy-pdf-word)
[![PHP](https://img.shields.io/packagist/php-v/biztecheg/easy-pdf-word)](https://packagist.org/packages/biztecheg/easy-pdf-word)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

Generate PDF and Word (.docx) documents in plain PHP, in any language, with first-class Arabic support and ready-made templates. No framework needed: it works in WordPress, Symfony, CodeIgniter or a plain script.

Using Laravel? Install [biztecheg/laravel-easy-pdf-word](https://github.com/BiztechEG/laravel-easy-pdf-word) instead. It is built on this package and adds the `Doc` facade, config, Blade views, storage disks, downloads, mail, queues and a preview page.

**Documentation:** [easypdf.biztech-eg.com](https://easypdf.biztech-eg.com) (English and Arabic).

- Arabic that renders correctly: joined letters, right-to-left layout, mixed Arabic and English, Arabic or Latin digits.
- Two PDF engines: **mPDF** (pure PHP, works on shared hosting) and **Chromium** (via Browsershot or Gotenberg), with automatic fallback.
- Word files with real right-to-left paragraphs and tables, from the same templates, from code, or from a .docx you design in Word.
- Twelve ready-made templates, each in PDF and Word: tax invoice (with ZATCA QR), Egyptian e-invoice (ETA), credit/debit note, price quotation, purchase order, delivery note, receipt/payment voucher, payslip, contract, certificate, official letter, table report.
- Arabic helpers: amounts in words (تفقيط), Hijri dates, Arabic numerals.
- Bundled Arabic fonts: Cairo, Tajawal and Noto Naskh Arabic.

[العربية](#بالعربي)

## Requirements

- PHP 8.2+
- ext-mbstring, ext-gd; ext-intl for Hijri dates; ext-zip for Word files and ZIP archives

## Installation

```bash
composer require biztecheg/easy-pdf-word

# Pick at least one PDF engine
composer require mpdf/mpdf              # default, pure PHP
composer require spatie/browsershot     # optional, Chromium

# For Word files
composer require phpoffice/phpword
```

## Usage

```php
use BiztechEG\EasyPdfWord\EasyPdfWord;

$docs = EasyPdfWord::create([
    'locale' => 'ar',
    'theme' => ['company' => ['name' => 'شركة بيزتك', 'tax_number' => '123-456-789']],
]);
```

`create()` takes the same settings as the Laravel package's `config/easy-pdf-word.php`; leave out what you do not change. `EasyPdfWord::defaults()` returns them all.

### A ready-made template

```php
$invoice = $docs->template('invoice', [
    'invoice' => ['number' => 'INV-1024', 'date' => '2026-10-08', 'currency' => 'EGP', 'tax_rate' => 14],
    'seller'  => ['name' => 'شركة بيزتك', 'tax_number' => '123-456-789'],
    'buyer'   => ['name' => 'مؤسسة النور'],
    'items'   => [
        ['description' => 'تطوير نظام', 'quantity' => 1, 'unit_price' => 25000],
    ],
]);

$invoice->pdf()->save(__DIR__.'/invoice.pdf');
$invoice->word()->save(__DIR__.'/invoice.docx');

// Or send it to the browser
$invoice->pdf('INV-1024.pdf')->send(inline: true);
```

The data is checked against the template's rules first. A mistake throws `BiztechEG\EasyPdfWord\Exceptions\ValidationFailed`, whose `errors()` lists the messages by field.

### A document built in code

```php
$docs->make()
    ->locale('ar')
    ->heading('تقرير المبيعات')
    ->paragraph('إجمالي المبيعات لشهر أكتوبر.')
    ->table([['البند', 'القيمة'], ['المبيعات', '120,000']], ['header' => true])
    ->pdf()
    ->save(__DIR__.'/report.pdf');
```

The same blocks make a Word file with `->word()`.

### Your own HTML or views

```php
$docs->html('<h1>مرحباً</h1>')->locale('ar')->pdf()->save(__DIR__.'/hello.pdf');

// views/contracts/show.html.php, with 'views' => ['paths' => [__DIR__.'/views']]
$docs->view('contracts.show', ['contract' => $contract])->pdf();
```

Views are plain PHP files. They get your data as variables and `$doc`, the document helpers: `$doc->e()` escapes a value, `$doc->money()`, `$doc->tafqeet()`, `$doc->hijri()` and `$doc->ltr()` format it for the document's language.

### Your own templates

A template is a folder with `template.php` (fields, defaults, sample data), a PDF page `pdf.html.php` or a code layout `layout.php`, optional `word.php` or `word.docx`, `footer.html.php` and `lang/*.php` labels. Point `templates.paths` at your folders:

```php
$docs = EasyPdfWord::create(['templates' => ['paths' => [__DIR__.'/doc-templates']]]);
```

The bundled templates in [resources/templates](resources/templates) are complete examples, and the [custom templates guide](https://easypdf.biztech-eg.com/guide/custom-templates) explains every file.

### Arabic helpers

```php
use BiztechEG\EasyPdfWord\Arabic\Arabic;

Arabic::tafqeet(1250.5, 'EGP');   // ألف ومائتان وخمسون جنيهاً وخمسون قرشاً
Arabic::hijri('2026-10-08');      // ٢٧ ربيع الآخر ١٤٤٨ هـ
Arabic::numerals('INV-2026');     // INV-٢٠٢٦
```

The global functions `tafqeet()`, `hijri_date()` and `arabic_numerals()` do the same.

### Your own fonts

```php
$docs = EasyPdfWord::create(['fonts' => ['custom' => [
    'my-font' => ['regular' => __DIR__.'/fonts/MyFont-Regular.ttf', 'bold' => __DIR__.'/fonts/MyFont-Bold.ttf', 'arabic' => true],
]]]);

$docs->template('letter', $data)->font('my-font')->pdf();
```

Some Arabic fonts need their tables fixed before mPDF can shape them:

```bash
python3 vendor/biztecheg/easy-pdf-word/bin/mpdf-font-fix.py fonts/MyFont-*.ttf
```

The bundled fonts are already fixed this way.

## Security

The package treats the data you pass to a template as untrusted: text is escaped in templates, documents built in code and Word files; local images are read only from the folders in `images.paths`; image URLs are ignored unless you allow their hosts; colours, locales and font names must be real values. HTML you pass to `html()` and the templates and views you write are code, and are used as written. See [SECURITY.md](SECURITY.md) to report a vulnerability.

## License

MIT. The bundled fonts are under the SIL Open Font License (see `resources/fonts`). mPDF, an optional engine, is GPL-2.0.

## بالعربي

مكتبة PHP لإنشاء ملفات PDF وWord بأي لغة، مع دعم كامل للعربية وقوالب جاهزة. لا تحتاج إلى إطار عمل، فتعمل في WordPress وSymfony وCodeIgniter أو في سكريبت PHP عادي. ولمشاريع Laravel استخدم [biztecheg/laravel-easy-pdf-word](https://github.com/BiztechEG/laravel-easy-pdf-word) المبنية على هذه المكتبة.

```bash
composer require biztecheg/easy-pdf-word mpdf/mpdf phpoffice/phpword
```

```php
use BiztechEG\EasyPdfWord\EasyPdfWord;

$docs = EasyPdfWord::create(['locale' => 'ar']);

$docs->template('invoice', $data)->pdf()->save(__DIR__.'/invoice.pdf');
$docs->template('invoice', $data)->word()->save(__DIR__.'/invoice.docx');
```

- عربي صحيح: حروف متصلة، واتجاه من اليمين لليسار، وأرقام عربية أو لاتينية.
- محركان لملفات PDF: mPDF (PHP خالص) وChromium، مع رجوع تلقائي إلى mPDF.
- اثنا عشر قالباً جاهزاً بصيغتي PDF وWord، منها الفاتورة الضريبية برمز ZATCA والفاتورة الإلكترونية المصرية.
- تفقيط المبالغ، والتاريخ الهجري، والأرقام العربية.

التوثيق الكامل بالعربية: [easypdf.biztech-eg.com/ar](https://easypdf.biztech-eg.com/ar/).
