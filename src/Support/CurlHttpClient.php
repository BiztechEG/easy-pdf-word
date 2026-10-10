<?php

namespace BiztechEG\EasyPdfWord\Support;

use BiztechEG\EasyPdfWord\Contracts\HttpClient;
use RuntimeException;

/**
 * HTTP without a framework, through the curl extension.
 */
final class CurlHttpClient implements HttpClient
{
    public function postMultipart(string $url, array $fields, array $files, int $timeout): HttpResponse
    {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('Sending documents to Gotenberg needs the PHP curl extension.');
        }

        $boundary = 'easy-pdf-word-'.bin2hex(random_bytes(12));
        $body = '';

        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"".self::quote($name)."\"\r\n\r\n{$value}\r\n";
        }

        // Built by hand: several files share one field name ("files"), which a PHP array cannot hold.
        foreach ($files as $file) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"".self::quote($file['name']).'"; filename="'.self::quote($file['filename'])."\"\r\n"
                ."Content-Type: text/html; charset=utf-8\r\n\r\n{$file['contents']}\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data; boundary='.$boundary],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 10),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);

        if ($response === false) {
            throw new RuntimeException("Could not reach {$url}: {$error}");
        }

        return new HttpResponse($status, (string) $response);
    }

    /** A name for a Content-Disposition header: no quotes or line breaks. */
    private static function quote(string $value): string
    {
        return str_replace(['"', "\r", "\n"], ['%22', '%0D', '%0A'], $value);
    }
}
