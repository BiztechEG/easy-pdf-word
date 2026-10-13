<?php

// A tiny image host for the remote image tests: /logo.png is a 1x1 PNG,
// /moved redirects to it, /outside.svg points at another URL. /echo answers with the request's content type and
// body (start the server with -d enable_post_data_reading=0 for multipart).
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC');

match (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    '/logo.png' => (function () use ($png) {
        header('Content-Type: image/png');
        echo $png;
    })(),
    '/outside.svg' => (function () {
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="http://127.0.0.1/x.png" width="9" height="9"/></svg>';
    })(),
    '/moved' => header('Location: /logo.png', true, 302),
    '/echo' => print(($_SERVER['CONTENT_TYPE'] ?? '')."\n\n".file_get_contents('php://input')),
    '/fail' => (function () {
        http_response_code(503);
        echo 'busy';
    })(),
    default => http_response_code(404),
};
