<?php

namespace BiztechEG\EasyPdfWord\Tests\Concerns;

/** PHP's built-in server running tests/fixtures/server/router.php, stopped after each test. */
trait BuiltInServer
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

    /** The server's host:port. */
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
