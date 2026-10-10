<?php

namespace BiztechEG\EasyPdfWord\Contracts;

use BiztechEG\EasyPdfWord\Support\HttpResponse;

/**
 * The one HTTP call the package makes: a multipart POST to a Gotenberg
 * server. Plain PHP uses curl; Laravel uses its Http client, so Http::fake()
 * works in tests.
 */
interface HttpClient
{
    /**
     * @param  array<string, string>  $fields  form fields
     * @param  list<array{name: string, contents: string, filename: string}>  $files
     */
    public function postMultipart(string $url, array $fields, array $files, int $timeout): HttpResponse;
}
