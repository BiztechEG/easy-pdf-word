<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Support\DocContext;
use BiztechEG\EasyPdfWord\Tests\Concerns\BuiltInServer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Images from user data reach the engines only as checked data URIs. */
class ImageSourceTest extends TestCase
{
    use BuiltInServer;

    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="http://127.0.0.1/x.png" width="9" height="9"/></svg>';

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC';

    public static function disguisedSvgs(): array
    {
        return [
            'labelled png' => ['data:image/png;base64,'.base64_encode(self::SVG)],
            'png with a parameter' => ['data:image/png;x=y;base64,'.base64_encode(self::SVG)],
            'labelled gif' => ['data:image/gif;base64,'.base64_encode(self::SVG)],
            'svg with a charset' => ['data:image/svg+xml;charset=utf-8;base64,'.base64_encode(self::SVG)],
            'upper case' => ['DATA:IMAGE/PNG;BASE64,'.base64_encode(self::SVG)],
        ];
    }

    #[DataProvider('disguisedSvgs')]
    public function test_svgs_under_another_type_are_still_checked(string $source): void
    {
        $this->assertNull($this->context()->image($source));
    }

    public function test_data_uris_are_rebuilt_with_their_real_type(): void
    {
        $this->assertSame('data:image/png;base64,'.self::PNG, $this->context()->image('data:image/jpeg;x=y;base64,'.self::PNG));
    }

    public function test_data_uris_that_are_not_images_are_refused(): void
    {
        $this->assertNull($this->context()->image('data:image/png;base64,'.base64_encode('not an image')));
        $this->assertNull($this->context()->image('data:text/html;base64,'.self::PNG));
    }

    public function test_allowed_urls_are_fetched_for_every_engine(): void
    {
        $host = $this->server();

        $this->assertSame('data:image/png;base64,'.self::PNG, $this->context(['127.0.0.1'])->image("http://{$host}/logo.png"));
    }

    public function test_remote_svgs_that_point_outside_themselves_are_refused(): void
    {
        $host = $this->server();

        $this->assertNull($this->context(['127.0.0.1'])->image("http://{$host}/outside.svg"));
    }

    public function test_remote_redirects_are_not_followed(): void
    {
        $host = $this->server();

        $this->assertNull($this->context(['127.0.0.1'])->image("http://{$host}/moved"));
    }

    public function test_urls_of_other_hosts_are_not_fetched(): void
    {
        $this->assertNull($this->context(['cdn.example.com'])->image('http://127.0.0.1/logo.png'));
    }

    private function context(bool|array $remoteImages = false): DocContext
    {
        return new DocContext('en', 'ltr', 'cairo', [], 'latin', 'mpdf', imagePaths: [], remoteImages: $remoteImages);
    }
}
