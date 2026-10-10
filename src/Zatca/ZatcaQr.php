<?php

namespace BiztechEG\EasyPdfWord\Zatca;

use BiztechEG\EasyPdfWord\Support\Dates;
use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Support\Qr;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * QR payload for Saudi simplified tax invoices (ZATCA phase 1): five TLV
 * fields, base64 encoded.
 *
 *   ZatcaQr::make('شركة المثال', '300000000000003', now(), 1150.00, 150.00)->toBase64();
 */
class ZatcaQr
{
    public function __construct(
        public readonly string $sellerName,
        public readonly string $vatNumber,
        public readonly string $timestamp,
        public readonly string $total,
        public readonly string $vatTotal,
    ) {}

    public static function make(
        string $sellerName,
        string $vatNumber,
        DateTimeInterface|string $timestamp,
        int|float|string $total,
        int|float|string $vatTotal,
    ): self {
        $time = match (true) {
            $timestamp instanceof DateTimeInterface => Dates::parse($timestamp),
            // A date alone ("2026-10-08") keeps its day: read as local
            // midnight, it would become the day before in UTC east of UTC.
            preg_match('#^\s*\d{4}[-/]\d{1,2}[-/]\d{1,2}\s*$#', $timestamp) === 1 => Dates::parse($timestamp, 'UTC'),
            default => Dates::parse($timestamp),
        };

        return new self(
            $sellerName,
            $vatNumber,
            $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            number_format(DocContext::toFloat($total), 2, '.', ''),
            number_format(DocContext::toFloat($vatTotal), 2, '.', ''),
        );
    }

    public function toTlv(): string
    {
        $fields = [$this->sellerName, $this->vatNumber, $this->timestamp, $this->total, $this->vatTotal];
        $tlv = '';

        foreach ($fields as $index => $value) {
            if (strlen($value) > 255) {
                throw new InvalidArgumentException('ZATCA QR field '.($index + 1).' is longer than 255 bytes: '.mb_substr($value, 0, 40).'...');
            }

            $tlv .= chr($index + 1).chr(strlen($value)).$value;
        }

        return $tlv;
    }

    public function toBase64(): string
    {
        return base64_encode($this->toTlv());
    }

    public function toDataUri(int $scale = 5): string
    {
        return Qr::dataUri($this->toBase64(), $scale);
    }

    /**
     * @return array<int, string> tag => value
     */
    public static function decode(string $base64): array
    {
        $bytes = base64_decode($base64, true) ?: '';
        $fields = [];

        for ($i = 0; $i + 1 < strlen($bytes);) {
            $tag = ord($bytes[$i]);
            $length = ord($bytes[$i + 1]);
            $fields[$tag] = substr($bytes, $i + 2, $length);
            $i += 2 + $length;
        }

        return $fields;
    }
}
