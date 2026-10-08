<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Zatca\ZatcaQr;
use PHPUnit\Framework\TestCase;

class ZatcaQrTest extends TestCase
{
    public function test_tlv_encoding(): void
    {
        $qr = ZatcaQr::make('Bobs Records', '310122393500003', '2022-04-25T15:30:00Z', 1000.00, 150.00);

        $this->assertSame(
            'AQxCb2JzIFJlY29yZHMCDzMxMDEyMjM5MzUwMDAwMwMUMjAyMi0wNC0yNVQxNTozMDowMFoEBzEwMDAuMDAFBjE1MC4wMA==',
            $qr->toBase64()
        );
    }

    public function test_arabic_seller_name_uses_byte_length(): void
    {
        $qr = ZatcaQr::make('شركة المثال', '300000000000003', '2026-10-08 12:00:00', 115, 15);

        $fields = ZatcaQr::decode($qr->toBase64());

        $this->assertSame('شركة المثال', $fields[1]);
        $this->assertSame('300000000000003', $fields[2]);
        $this->assertSame('115.00', $fields[4]);
        $this->assertSame('15.00', $fields[5]);
        $this->assertStringStartsWith('data:image/png;base64,', $qr->toDataUri());
    }

    public function test_fields_longer_than_255_bytes_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ZatcaQr::make(str_repeat('ش', 130), '300000000000003', '2026-10-08', 100, 15)->toTlv();
    }
}
