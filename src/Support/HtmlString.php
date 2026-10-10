<?php

namespace BiztechEG\EasyPdfWord\Support;

/*
| HTML that views print as is. Inside Laravel it extends Laravel's
| HtmlString, so Blade's {{ }} leaves it unescaped and existing type checks
| keep passing; without Laravel it is a plain Stringable.
*/

if (class_exists(\Illuminate\Support\HtmlString::class)) {
    class HtmlString extends \Illuminate\Support\HtmlString {}
} else {
    class HtmlString implements \Stringable
    {
        public function __construct(protected string $html = '') {}

        public function toHtml(): string
        {
            return $this->html;
        }

        public function isEmpty(): bool
        {
            return $this->html === '';
        }

        public function isNotEmpty(): bool
        {
            return ! $this->isEmpty();
        }

        public function __toString(): string
        {
            return $this->toHtml();
        }
    }
}
