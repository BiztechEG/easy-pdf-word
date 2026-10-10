<?php

namespace BiztechEG\EasyPdfWord\Support;

final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {}

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
