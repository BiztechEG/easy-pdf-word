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

Local images (a logo, a signature) are read only from the folders you list, and none are listed by default. Add yours, or the image is left out with a warning in the PHP error log:

```php
$docs = EasyPdfWord::create([
    'images' => ['paths' => [__DIR__.'/images']],
    'theme' => ['logo' => __DIR__.'/images/logo.png'],
]);
```

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

Text can be a list of runs, each with its own style: `bold`, `italic`, `size`, `color` and `background` (a hex value, `rgb()` or a CSS colour name such as `navy`), `underline`, `strike`, `link` (http, https or mailto), `font` and `ltr`. They look the same in PDF and Word:

```php
$docs->make()->locale('ar')->paragraph([
    ['text' => 'السعر شامل الضريبة ', 'bold' => true],
    ['text' => '12,000', 'strike' => true, 'color' => 'gray'],
    ['text' => ' 10,000 ريال', 'background' => '#FEF3C7'],
    ['text' => ' — التفاصيل على '],
    ['text' => 'موقعنا', 'link' => 'https://biztech.example/offers'],
]);
```

Lists are numbered or bulleted from the start side, with sub-lists, in PDF and Word:

```php
$docs->make()->locale('ar')->list([
    'الأسعار لا تشمل ضريبة القيمة المضافة.',
    'يسري العرض 30 يوماً.',
    ['التسليم في:', ['الموقع أ', 'الموقع ب']],
], ['ordered' => true]);
```

An item is a string, a run, a list of runs, or `[item, [sub-items]]`. `start` sets the first number, and `size`, `color`, `bold`, `italic` and `space_after` style the list.

A `font` must be registered (see [Your own fonts](#your-own-fonts)): the PDF has no file for other names, so they are left out and reported through `warn`. In Word, a registered font becomes its `word` name, or the Word font.

A table row can have its own style, be a total row (bold, with a line above it) or stay on one page with the next row, so a section heading never ends a page:

```php
use BiztechEG\EasyPdfWord\Builder\DocumentBuilder as B;

$docs->make()->locale('ar')->table([
    ['البند', 'الكمية', 'الإجمالي'],
    B::row([['text' => 'أعمال مدنية', 'colspan' => 3]], ['style' => ['bold' => true, 'background' => '#F1F5F9'], 'keep_with_next' => true]),
    ['حفر', '120', '10,200.00'],
    B::row([['text' => 'المجموع', 'colspan' => 2], '10,200.00'], ['total' => true]),
], ['header' => true]);
```

A cell's own style wins over its row's. Several rows can be totals; `footer => true` still makes the last row bold.

A cell's `overlay` draws a stamp over it, behind its text, such as a company stamp over the signature:

```php
[['lines' => ['مدير المبيعات', '', 'عمرو محمد'], 'overlay' => ['image' => $stamp, 'width' => 35, 'offset' => [5, 2]]]]
```

`width` and `height` are in mm, and the image keeps its proportions unless `stretch => true`. `offset` is `[start, down]` in mm from the cell's start-top corner: a positive start moves toward the right in Arabic. The image follows the document's image rules. In PDF the stamp is the cell's background, so what lies past the cell's edges is cut off; in Word it floats behind the text.

Tables take `borders` (`'rows'`, the default, `'grid'` or `'none'`), `border_width` in points, `padding` in mm and `valign` (`top`, `middle`, `bottom`); a cell can set its own `padding` and `valign`. `keep_together => true` keeps a small table, such as the totals, on one page. A cell with `rowspan` covers the cells below it, written the HTML way:

```php
$docs->make()->locale('ar')->table([
    ['القسم', 'البند', 'الإجمالي'],
    [['text' => 'أعمال مدنية', 'rowspan' => 2, 'valign' => 'middle'], 'حفر', '10,200.00'],
    ['ردم', '4,000.00'],
], ['header' => true, 'borders' => 'grid', 'padding' => 3]);
```

In tests, `->blocks()` gives a document's body blocks and `->text()` its text, headers and footers first, without rendering: table cells are joined with tabs, rows with new lines, and rich text is reduced to its text. A template's `layout.php` or `word.php` runs with the data; views and HTML have no blocks.

```php
$this->assertStringContainsString("الإجمالي\t33,580.00", $docs->template('quotation', $data)->text());
```

### Headers and footers

`header()` and `footer()` take HTML, or blocks built in code. Blocks work in PDF and Word, so a letterhead with a logo in a table looks the same in both, and the first page can have its own:

```php
use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;

$docs->make()->locale('ar')
    ->header(fn (DocumentBuilder $h) => $h->table(
        [[['image' => __DIR__.'/images/logo.png', 'width' => 30], ['lines' => ['شركة بيزتك', 'الدقي، الجيزة']]]],
        ['borders' => false, 'columns' => [30, 70]],
    ))
    ->footer(fn (DocumentBuilder $f) => $f->paragraph('صفحة {page} من {pages}', ['align' => 'center', 'size' => 8]))
    ->firstPageHeader(fn (DocumentBuilder $h) => $h->table($fullLetterhead, ['borders' => false]))
    ->firstPageFooter(null)            // no footer on page 1
    ->headerDistance(6);               // mm from the top of the page
```

A header or footer takes `paragraph`, `table`, `image`, `line` and `spacer` blocks, and its text may use `{page}` and `{pages}`. With mPDF, a header taller than the top margin pushes the body down, as in Word. Chromium and Gotenberg give every page the same header and footer. Templates can hold the same as `header.layout.php`, `footer.layout.php`, `first-header.layout.php` and `first-footer.layout.php`.

### Your own HTML or views

```php
$docs->html('<h1>مرحباً</h1>')->locale('ar')->pdf()->save(__DIR__.'/hello.pdf');

// views/contracts/show.html.php, with 'views' => ['paths' => [__DIR__.'/views']]
$docs->view('contracts.show', ['contract' => $contract])->pdf();
```

Views are plain PHP files. They get your data as variables and `$doc`, the document helpers: `$doc->e()` escapes a value, `$doc->money()`, `$doc->date()`, `$doc->inWords()`, `$doc->hijri()` and `$doc->ltr()` format it for the document's language.

### HTML from a rich-text editor

`richText()` adds HTML that users write in an editor to a document built in code, such as the terms of a price offer. The HTML is cleaned first, so it is safe to pass as it is, unlike `html()`:

```php
$docs->make()->locale('ar')
    ->richText($offer->preamble)
    ->table($rows, ['header' => true])
    ->richText($offer->terms, ['font_size' => 10])
    ->pdf();
```

Headings take the builder's sizes, tables get borders around every cell, and the block follows the document's direction and numerals. Its images must be data URIs or allowed URLs (`images.remote`); paths to files on the server are refused, even in `images.paths`. Word files keep the formatting too: headings, lists numbered from the right in Arabic, quotes, tables with merged cells, links and images. If Word cannot take a block, its text is written instead and `warn` is told.

To clean the HTML yourself, before storing it for example, `Support\HtmlSanitizer` cleans HTML that users write in an editor such as TipTap or CKEditor. It keeps headings, paragraphs, bold, italic, underline, strike-through, highlight, colours, alignment, lists, quotes, links, tables and images, and removes scripts, event attributes, other styles and links that are not http, https or mailto:

```php
use BiztechEG\EasyPdfWord\Support\HtmlSanitizer;

$clean = (new HtmlSanitizer)->sanitize($request['terms']);
```

By default images are kept only when they are http(s) URLs or PNG, JPEG, GIF or WebP data URIs; pass a closure as the first argument to check them your way. The input is limited to 256 KB, 20 levels of nesting and tables of 200 rows and 20 columns; what goes beyond is cut, and the optional `warn` closure is told.

### Your own templates

A template is a folder with `template.php` (fields, defaults, sample data), a PDF page `pdf.html.php` or a code layout `layout.php`, optional `word.php` or `word.docx`, `header.html.php` and `footer.html.php` or their `*.layout.php` versions built in code, and `lang/*.json` labels (or `lang/*.php`). Point `templates.paths` at your folders:

```php
$docs = EasyPdfWord::create(['templates' => ['paths' => [__DIR__.'/doc-templates']]]);
```

The bundled templates in [resources/templates](resources/templates) are complete examples, and the [custom templates guide](https://easypdf.biztech-eg.com/guide/custom-templates) explains every file.

### Languages

The bundled templates come in Arabic, English and Turkish:

```php
$docs->template('invoice', $data)->locale('tr')->pdf();   // Fatura, 08.10.2026, 1.250,50, KDV (%20)
```

What the library knows about a language lives in JSON files, so other versions of the library can read the same files:

- [resources/lang](resources/lang)`/{language}.json`: month and day names, date and number formats, currency labels and how amounts are written in words ("Yalnız bin iki yüz Türk Lirası").
- `resources/templates/{template}/lang/{language}.json`: each template's labels.

To add a language, add both: a language file in a folder of yours, and a `lang/{language}.json` in your copy of each template you use.

```php
use BiztechEG\EasyPdfWord\Support\Language;

Language::addPath(__DIR__.'/lang');   // lang/fr.json; keys left out come from English
```

Amounts in words in languages other than Arabic need ext-intl. The Cairo and Noto Naskh fonts have the Turkish letters; Tajawal does not.

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

Word files do not carry fonts. Add `'word' => 'My Font'` to a font when your readers have it installed: a run with `'font' => 'my-font'` then uses it in Word, and otherwise the Word font (`word.font`, Arial by default).

Some Arabic fonts need their tables fixed before mPDF can shape them:

```bash
python3 vendor/biztecheg/easy-pdf-word/bin/mpdf-font-fix.py fonts/MyFont-*.ttf
```

The bundled fonts are already fixed this way.

## Security

The package treats the data you pass to a template as untrusted: text is escaped in templates, documents built in code and Word files; local images are read only from the folders in `images.paths`; image URLs are ignored unless you allow their hosts; colours, locales and font names must be real values. HTML you pass to `html()` and the templates and views you write are code, and are used as written; clean HTML from users with `HtmlSanitizer` first. See [SECURITY.md](SECURITY.md) to report a vulnerability.

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
