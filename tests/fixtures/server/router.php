<?php

// A tiny image host for the remote image tests: /logo.png is a 1x1 PNG,
// /moved redirects to it.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC');

match (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    '/logo.png' => (function () use ($png) {
        header('Content-Type: image/png');
        echo $png;
    })(),
    '/moved' => header('Location: /logo.png', true, 302),
    default => http_response_code(404),
};
