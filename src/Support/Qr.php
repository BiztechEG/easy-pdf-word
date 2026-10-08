<?php

namespace BiztechEG\EasyPdfWord\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class Qr
{
    /**
     * A PNG data URI, which every engine (mPDF and Chromium) can show in <img>.
     */
    public static function dataUri(string $value, int $scale = 5): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'scale' => $scale,
            'quietzoneSize' => 1,
        ]);

        return (new QRCode($options))->render($value);
    }
}
