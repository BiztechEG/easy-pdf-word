<?php

namespace BiztechEG\EasyPdfWord\Contracts;

/**
 * Turns a template's PHP view (pdf.html.php, footer.html.php) or an app
 * view into HTML. Plain PHP includes the file; Laravel uses its view
 * factory, so Blade views keep working there.
 */
interface ViewRenderer
{
    /** Render a view file by its full path. */
    public function file(string $path, array $data = []): string;

    /** Render a view by name: "invoices.show" in Laravel, or a file under the renderer's folders. */
    public function view(string $name, array $data = []): string;
}
