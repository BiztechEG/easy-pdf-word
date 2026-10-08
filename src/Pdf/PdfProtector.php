<?php

namespace BiztechEG\EasyPdfWord\Pdf;

use BiztechEG\EasyPdfWord\Exceptions\DriverNotAvailable;
use BiztechEG\EasyPdfWord\Pdf\Drivers\MpdfDriver;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Adds a password to a PDF made by an engine that cannot encrypt (Chromium):
 * mPDF copies its pages, which stay vector text, into an encrypted file.
 * Links inside the pages do not survive the copy.
 */
class PdfProtector
{
    public function __construct(private ?string $tempDir = null) {}

    public function protect(string $pdf, PdfOptions $options): string
    {
        if (! class_exists(Mpdf::class)) {
            throw new DriverNotAvailable('PDF passwords need the mpdf/mpdf package, also with Chromium. Run: composer require mpdf/mpdf');
        }

        $tempDir = $this->tempDir ?: sys_get_temp_dir().'/easy-pdf-word';

        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf(['tempDir' => $tempDir, 'margin_top' => 0, 'margin_right' => 0, 'margin_bottom' => 0, 'margin_left' => 0]);
        $pages = $mpdf->setSourceFile(StreamReader::createByString($pdf));

        for ($page = 1; $page <= $pages; $page++) {
            $template = $mpdf->importPage($page);
            ['width' => $width, 'height' => $height] = $mpdf->getTemplateSize($template);

            // Like the mPDF engine: the upright size, turned by the orientation.
            $mpdf->AddPageByArray([
                'sheet-size' => [min($width, $height), max($width, $height)],
                'orientation' => $width > $height ? 'L' : 'P',
            ]);
            $mpdf->useTemplate($template);
        }

        if ($options->title) {
            $mpdf->SetTitle($options->title);
        }

        if ($options->author) {
            $mpdf->SetAuthor($options->author);
        }

        MpdfDriver::protect($mpdf, $options->protection);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
