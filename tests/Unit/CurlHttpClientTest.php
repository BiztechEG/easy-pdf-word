<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Support\CurlHttpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** The curl client plain PHP uses for Gotenberg, against PHP's built-in server. */
class CurlHttpClientTest extends TestCase
{
    /** @var resource|null */
    private $server = null;

    protected function tearDown(): void
    {
        if ($this->server) {
            proc_terminate($this->server);
        }

        parent::tearDown();
    }

    public function test_it_posts_fields_and_files_with_the_same_name(): void
    {
        $response = (new CurlHttpClient)->postMultipart('http://'.$this->server().'/echo', ['paperWidth' => '8.2677'], [
            ['name' => 'files', 'contents' => '<p dir="rtl">مرحبا</p>', 'filename' => 'index.html'],
            ['name' => 'files', 'contents' => '<div>{page}</div>', 'filename' => 'footer.html'],
        ], 10);

        $this->assertSame(200, $response->status);
        $this->assertTrue($response->successful());

        [$type, $body] = explode("\n\n", $response->body, 2);
        $this->assertMatchesRegularExpression('/^multipart\/form-data; boundary=(\S+)$/', $type);
        $boundary = substr($type, strlen('multipart/form-data; boundary='));
        $parts = array_slice(explode("--{$boundary}", $body), 1, -1);

        $this->assertCount(3, $parts);
        $this->assertStringContainsString("name=\"paperWidth\"\r\n\r\n8.2677\r\n", $parts[0]);
        $this->assertStringContainsString('name="files"; filename="index.html"', $parts[1]);
        $this->assertStringContainsString("\r\n\r\n<p dir=\"rtl\">مرحبا</p>\r\n", $parts[1]);
        $this->assertStringContainsString('name="files"; filename="footer.html"', $parts[2]);
    }

    public function test_error_statuses_are_returned_not_thrown(): void
    {
        $response = (new CurlHttpClient)->postMultipart('http://'.$this->server().'/fail', [], [], 10);

        $this->assertSame(503, $response->status);
        $this->assertFalse($response->successful());
        $this->assertSame('busy', $response->body);
    }

    public function test_an_unreachable_server_throws(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Could not reach http://{$address}/");

        (new CurlHttpClient)->postMultipart("http://{$address}/", [], [], 5);
    }

    public function test_only_web_urls_are_followed(): void
    {
        $this->expectException(RuntimeException::class);

        (new CurlHttpClient)->postMultipart('file:///etc/passwd', [], [], 5);
    }

    private function server(): string
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $router = __DIR__.'/../fixtures/server/router.php';
        $this->server = proc_open(
            [PHP_BINARY, '-d', 'enable_post_data_reading=0', '-S', $address, $router],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        );

        for ($i = 0; $i < 50 && ! @fsockopen('127.0.0.1', (int) substr(strrchr($address, ':'), 1)); $i++) {
            usleep(100_000);
        }

        return $address;
    }
}
