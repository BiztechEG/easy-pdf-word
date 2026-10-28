<?php

namespace BiztechEG\EasyPdfWord\Support;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Cleans HTML from a rich-text editor (TipTap, CKEditor, a pasted page) so it
 * can be printed in a document: only text, lists, tables, links and images
 * are kept, with a short list of styles, and nothing that runs code, loads
 * files or reaches the network.
 *
 *   $clean = (new HtmlSanitizer)->sanitize($request->input('terms'));
 *
 * The result is well-formed XHTML in one shape, read the same way by the PDF
 * and the Word renderer: strike-through and highlight are spans with a style,
 * list items hold no paragraphs, and the only entities are &lt; &gt; &amp;
 * and &quot;.
 */
final class HtmlSanitizer
{
    /** Input beyond this many bytes is cut. */
    public const MAX_BYTES = 262144;

    /** Elements nested deeper than this lose their tags and keep their text. */
    public const MAX_DEPTH = 20;

    public const MAX_TABLE_ROWS = 200;

    public const MAX_TABLE_COLUMNS = 20;

    /** The largest colspan and rowspan. */
    public const MAX_SPAN = 20;

    /** Tags kept, with the attributes each one may have besides style and dir. */
    private const TAGS = [
        'p' => [], 'br' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 'span' => [],
        'a' => ['href'], 'blockquote' => [], 'hr' => [],
        'ul' => [], 'ol' => ['start'], 'li' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'img' => ['src', 'width', 'height'],
    ];

    /** Removed with everything inside them. */
    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'noscript', 'title', 'colgroup'];

    /** Tags written as another tag. */
    private const RENAMED = ['s' => 'span', 'del' => 'span', 'strike' => 'span', 'mark' => 'span', 'h5' => 'h4', 'h6' => 'h4', 'tfoot' => 'tbody'];

    /**
     * Unknown block tags. They become paragraphs when they hold only text,
     * so <div>a</div><div>b</div> doesn't run into "ab", and are unwrapped
     * when they hold other blocks.
     */
    private const BLOCKS = ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav', 'address', 'figure', 'figcaption', 'pre', 'center', 'dl', 'dt', 'dd', 'details', 'summary', 'form', 'fieldset', 'legend', 'caption'];

    /** Kept tags that start a new block. */
    private const KEPT_BLOCKS = ['p', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'hr', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];

    /** Where text that is only white space means nothing. */
    private const STRUCTURES = ['ul', 'ol', 'table', 'thead', 'tbody', 'tr'];

    /** Where an unknown block may become a paragraph. */
    private const PARAGRAPH_PARENTS = ['root', 'blockquote', 'li', 'td', 'th'];

    private const ALIGNS = ['left', 'right', 'center', 'justify', 'start', 'end'];

    private readonly Closure $image;

    /** @var array<string, true> the limits already reported in this call */
    private array $reported = [];

    /**
     * @param  Closure(string): ?string|null  $image  checks each image source and returns the one to use, or null
     *                                                 to drop the image; by default http(s) URLs and PNG, JPEG, GIF and
     *                                                 WebP data URIs are kept as they are
     * @param  Closure(string): void|null  $warn  told when an image is dropped or a limit cuts the HTML
     * @param  float  $contentWidth  the width of the page's text, in mm: widths and images are capped at it
     */
    public function __construct(
        ?Closure $image = null,
        private readonly ?Closure $warn = null,
        private readonly float $contentWidth = 180,
    ) {
        $this->image = $image ?? fn (string $src): ?string => preg_match('#^(https?://|data:image/(png|jpe?g|gif|webp)[;,])#i', $src) ? $src : null;
    }

    public function sanitize(string $html): string
    {
        $this->reported = [];

        if (strlen($html) > self::MAX_BYTES) {
            $html = mb_strcut($html, 0, self::MAX_BYTES, 'UTF-8');
            $this->report('size', 'it is over '.(self::MAX_BYTES / 1024).' KB');
        }

        if (trim($html) === '') {
            return '';
        }

        $source = $this->parse($html);
        $body = $source->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        $out = new DOMDocument('1.0', 'UTF-8');
        $root = $out->createElement('root');
        $out->appendChild($root);
        $this->children($body, $root, 1);

        $this->fixStructure($root);
        $this->unwrapListParagraphs($root);
        $this->moveTablesOutOfLists($root);
        $this->limitTables($root);
        $this->trimBlocks($root);

        return $this->serialize($root);
    }

    private function parse(string $html): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $errors = libxml_use_internal_errors(true);

        try {
            // With the body opened first, an unknown first tag isn't put in the head.
            $dom->loadHTML('<html><head><meta charset="utf-8"></head><body>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($errors);
        }

        return $dom;
    }

    /** Copies the clean children of $from into $to. */
    private function children(DOMNode $from, DOMElement $to, int $depth): void
    {
        foreach (iterator_to_array($from->childNodes) as $node) {
            if ($node instanceof DOMText) {
                $this->text($node, $to);
            } elseif ($node instanceof DOMElement) {
                $this->element($node, $to, $depth);
            }
        }
    }

    private function text(DOMText $node, DOMElement $to): void
    {
        $text = self::clean($node->data);
        $text = (string) preg_replace('/[ \t\n\r\f]+/', ' ', $text);

        if ($text === '') {
            return;
        }

        // White space between blocks, or around a block, is only layout.
        if ($text === ' ' && (in_array($to->nodeName, self::STRUCTURES, true) || $this->isBlock($node->previousSibling) || $this->isBlock($node->nextSibling))) {
            return;
        }

        $to->appendChild($to->ownerDocument->createTextNode($text));
    }

    private function element(DOMElement $node, DOMElement $to, int $depth): void
    {
        $name = strtolower($node->nodeName);

        if (in_array($name, self::DROPPED, true)) {
            return;
        }

        if ($depth > self::MAX_DEPTH) {
            $this->report('depth', 'it is nested more than '.self::MAX_DEPTH.' levels deep');
            $this->children($node, $to, $depth + 1);

            return;
        }

        $tag = self::RENAMED[$name] ?? $name;

        if (in_array($name, self::BLOCKS, true)) {
            $tag = ! $this->hasBlocks($node) && in_array($to->nodeName, self::PARAGRAPH_PARENTS, true) ? 'p' : null;
        }

        if ($tag === null || ! isset(self::TAGS[$tag])) {
            $this->children($node, $to, $depth + 1);

            return;
        }

        $style = $this->style((string) $node->getAttribute('style'), $tag);

        // The shape both renderers read: strike-through and highlight as styles.
        if (in_array($name, ['s', 'del', 'strike'], true)) {
            $style['text-decoration'] = str_contains($style['text-decoration'] ?? '', 'underline') ? 'underline line-through' : 'line-through';
        } elseif ($name === 'mark') {
            $style['background-color'] ??= '#FFFF00';
        }

        $element = $to->ownerDocument->createElement($tag);

        if ($style !== []) {
            $element->setAttribute('style', implode('; ', array_map(fn ($property, $value) => $property.': '.$value, array_keys($style), $style)));
        }

        $dir = strtolower(trim((string) $node->getAttribute('dir')));

        if (in_array($dir, ['rtl', 'ltr'], true)) {
            $element->setAttribute('dir', $dir);
        }

        foreach (self::TAGS[$tag] as $attribute) {
            $value = $this->attribute($attribute, $node);

            if ($value !== null) {
                $element->setAttribute($attribute, $value);
            }
        }

        if ($tag === 'img') {
            if ($element->hasAttribute('src')) {
                $this->imageSize($element);
                $to->appendChild($element);
            }

            return;
        }

        if (! in_array($tag, ['br', 'hr'], true)) {
            $this->children($node, $element, $depth + 1);
        }

        // A link without an allowed address, or a span without a style, is only its text.
        if (($tag === 'a' && ! $element->hasAttribute('href')) || ($tag === 'span' && ! $element->hasAttributes())) {
            while ($element->firstChild !== null) {
                $to->appendChild($element->firstChild);
            }

            return;
        }

        $to->appendChild($element);
    }

    /** An allowed attribute's clean value, or null. */
    private function attribute(string $name, DOMElement $node): ?string
    {
        if (! $node->hasAttribute($name)) {
            return null;
        }

        $value = (string) $node->getAttribute($name);

        return match ($name) {
            'href' => self::href(self::clean($value)),
            'src' => $this->src($value),
            'colspan', 'rowspan' => $this->span($value),
            'start' => preg_match('/^\s*\d{1,6}\s*$/', $value) ? (string) (int) $value : null,
            'width', 'height' => preg_match('/^\s*(\d+(\.\d+)?)\s*(px)?\s*$/i', $value, $m) && (float) $m[1] > 0 ? (string) (int) round((float) $m[1]) : null,
            default => null,
        };
    }

    /**
     * http, https and mailto links only. The scheme is read the way a browser
     * reads it, so "jav&#x09;ascript:" is still javascript.
     */
    private static function href(string $value): ?string
    {
        $strip = fn (string $url) => (string) preg_replace('/[\x00-\x20\x7F]+|\p{Z}+/u', '', $url);

        if (! preg_match('#^(https?://[^/]|mailto:.)#i', $strip(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')))) {
            return null;
        }

        $url = $strip($value);

        return preg_match('#^(https?://[^/]|mailto:.)#i', $url) ? $url : null;
    }

    private function src(string $value): ?string
    {
        $value = trim(self::clean($value));

        if ($value === '') {
            return null;
        }

        $src = ($this->image)($value);

        if ($src === null || $src === '') {
            $this->report('image:'.md5($value), 'an image was dropped ['.mb_strimwidth((string) preg_replace('/[\x00-\x1F\x7F]/', '', $value), 0, 100, '...', 'UTF-8').']');

            return null;
        }

        return $src;
    }

    private function span(string $value): ?string
    {
        if (! preg_match('/^\s*(\d+)\s*$/', $value, $m) || (int) $m[1] <= 1) {
            return null;
        }

        if ((int) $m[1] > self::MAX_SPAN) {
            $this->report('span', 'a cell spans more than '.self::MAX_SPAN.' rows or columns');

            return (string) self::MAX_SPAN;
        }

        return (string) (int) $m[1];
    }

    /** width and height in px, capped at the text's width with the image's proportions kept. */
    private function imageSize(DOMElement $img): void
    {
        $max = (int) floor($this->contentWidth / 25.4 * 96);
        $width = (int) $img->getAttribute('width');
        $height = (int) $img->getAttribute('height');

        if ($width > $max) {
            $height = $height > 0 ? (int) round($height * $max / $width) : 0;
            $width = $max;
        }

        if ($height > $max) {
            $width = $width > 0 ? (int) round($width * $max / $height) : 0;
            $height = $max;
        }

        foreach (['width' => $width, 'height' => $height] as $attribute => $value) {
            $value > 0 ? $img->setAttribute($attribute, (string) $value) : $img->removeAttribute($attribute);
        }
    }

    /**
     * The allowed declarations of a style attribute, by property.
     *
     * @return array<string, string>
     */
    private function style(string $css, string $tag): array
    {
        $css = (string) preg_replace('#/\*.*?(\*/|\z)#s', '', self::clean($css));
        $style = [];

        foreach (explode(';', $css) as $declaration) {
            if (! str_contains($declaration, ':') || preg_match('/\\\\|url\(|expression\(|var\(|attr\(|!\s*important/i', $declaration)) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $value = $this->styleValue($property, $value, $tag);

            if ($value !== null) {
                $style[$property] = $value;
            }
        }

        return $style;
    }

    private function styleValue(string $property, string $value, string $tag): ?string
    {
        $lower = strtolower((string) preg_replace('/\s+/', ' ', $value));

        return match ($property) {
            'color', 'background-color' => Color::isValid($value) && ($hex = Color::hex($value)) !== null ? '#'.$hex : null,
            'text-align' => in_array($lower, self::ALIGNS, true) ? $lower : null,
            'direction' => in_array($lower, ['rtl', 'ltr'], true) ? $lower : null,
            'font-weight' => in_array($lower, ['bold', 'normal', '400', '700'], true) ? $lower : null,
            'font-style' => in_array($lower, ['italic', 'normal'], true) ? $lower : null,
            'text-decoration' => match ($lower) {
                'underline', 'line-through', 'none' => $lower,
                'underline line-through', 'line-through underline' => 'underline line-through',
                default => null,
            },
            'width' => in_array($tag, ['table', 'td', 'th'], true) ? $this->width($lower) : null,
            default => null,
        };
    }

    /** A width in %, mm or px, capped at the whole width. */
    private function width(string $value): ?string
    {
        if (! preg_match('/^(\d+(\.\d+)?)(%|mm|px)$/', $value, $m) || (float) $m[1] <= 0) {
            return null;
        }

        $max = match ($m[3]) {
            '%' => 100.0,
            'mm' => $this->contentWidth,
            'px' => floor($this->contentWidth / 25.4 * 96),
        };

        return self::number(min((float) $m[1], $max)).$m[3];
    }

    /**
     * Repairs what the parser lets through: list items outside a list,
     * cells outside a row, rows outside a table.
     */
    private function fixStructure(DOMElement $root): void
    {
        foreach ($this->elements($root, 'thead', 'tbody') as $section) {
            if (($section->parentNode?->nodeName ?? '') !== 'table') {
                $this->unwrap($section);
            }
        }

        foreach ($this->elements($root, 'tr') as $row) {
            if (! in_array($row->parentNode?->nodeName ?? '', ['table', 'thead', 'tbody'], true)) {
                $this->unwrap($row);
            }
        }

        foreach ($this->elements($root, 'td', 'th') as $cell) {
            if (($cell->parentNode?->nodeName ?? '') !== 'tr') {
                $this->hasBlocks($cell) ? $this->unwrap($cell) : $this->rename($cell, 'p');
            }
        }

        foreach ($this->elements($root, 'li') as $item) {
            if (! in_array($item->parentNode?->nodeName ?? '', ['ul', 'ol'], true)) {
                $this->hasBlocks($item) ? $this->unwrap($item) : $this->rename($item, 'p');
            }
        }

        // Text and tags directly in a list or a table have no place to go.
        foreach ($this->elements($root, 'ul', 'ol', 'table', 'thead', 'tbody', 'tr') as $parent) {
            $allowed = match ($parent->nodeName) {
                'ul', 'ol' => ['li'],
                'table' => ['thead', 'tbody', 'tr'],
                'thead', 'tbody' => ['tr'],
                'tr' => ['td', 'th'],
            };

            foreach (iterator_to_array($parent->childNodes) as $child) {
                if (! in_array($child->nodeName, $allowed, true)) {
                    $parent->removeChild($child);
                }
            }
        }
    }

    /**
     * TipTap wraps the text of every list item in <p>. The paragraphs are
     * unwrapped, joined by a line break, and the first one's alignment and
     * direction move to the item.
     */
    private function unwrapListParagraphs(DOMElement $root): void
    {
        foreach ($this->elements($root, 'li') as $item) {
            $first = true;

            foreach (iterator_to_array($item->childNodes) as $child) {
                if ($child->nodeName !== 'p') {
                    continue;
                }

                /** @var DOMElement $child */
                if ($first) {
                    foreach (['style', 'dir'] as $attribute) {
                        if ($child->hasAttribute($attribute) && ! $item->hasAttribute($attribute)) {
                            $item->setAttribute($attribute, $child->getAttribute($attribute));
                        }
                    }
                }

                if ($child->previousSibling !== null && ! $this->isBlock($child->previousSibling)) {
                    $item->insertBefore($item->ownerDocument->createElement('br'), $child);
                }

                if ($child->nextSibling !== null && ! $this->isBlock($child->nextSibling)) {
                    $item->insertBefore($item->ownerDocument->createElement('br'), $child->nextSibling);
                }

                $this->unwrap($child);
                $first = false;
            }
        }
    }

    /** Word can't put a table in a list item, so a table moves to after its list. */
    private function moveTablesOutOfLists(DOMElement $root): void
    {
        foreach ($this->elements($root, 'table') as $table) {
            $list = null;

            for ($node = $table->parentNode; $node !== null && ! in_array($node->nodeName, ['td', 'th', 'root'], true); $node = $node->parentNode) {
                if (in_array($node->nodeName, ['ul', 'ol'], true)) {
                    $list = $node;
                }
            }

            if ($list === null) {
                continue;
            }

            $after = $list;

            while ($after->nextSibling instanceof DOMElement && $after->nextSibling->getAttribute('data-moved') === '1') {
                $after = $after->nextSibling;
            }

            $table->setAttribute('data-moved', '1');
            $list->parentNode->insertBefore($table, $after->nextSibling);
        }

        foreach ($this->elements($root, 'table') as $table) {
            $table->removeAttribute('data-moved');
        }
    }

    private function limitTables(DOMElement $root): void
    {
        foreach ($this->elements($root, 'table') as $table) {
            $rows = [];

            foreach ($table->childNodes as $child) {
                if ($child->nodeName === 'tr') {
                    $rows[] = $child;
                } elseif (in_array($child->nodeName, ['thead', 'tbody'], true)) {
                    foreach ($child->childNodes as $row) {
                        $rows[] = $row;
                    }
                }
            }

            foreach (array_slice($rows, self::MAX_TABLE_ROWS) as $row) {
                $this->report('rows', 'a table has more than '.self::MAX_TABLE_ROWS.' rows');
                $row->parentNode->removeChild($row);
            }

            foreach (array_slice($rows, 0, self::MAX_TABLE_ROWS) as $row) {
                $columns = 0;

                foreach (iterator_to_array($row->childNodes) as $cell) {
                    /** @var DOMElement $cell */
                    $span = max(1, (int) $cell->getAttribute('colspan'));

                    if ($columns + $span > self::MAX_TABLE_COLUMNS) {
                        $this->report('columns', 'a table has more than '.self::MAX_TABLE_COLUMNS.' columns');
                        $left = self::MAX_TABLE_COLUMNS - $columns;

                        if ($left <= 0) {
                            $row->removeChild($cell);

                            continue;
                        }

                        $left > 1 ? $cell->setAttribute('colspan', (string) $left) : $cell->removeAttribute('colspan');
                        $span = $left;
                    }

                    $columns += $span;
                }
            }
        }
    }

    /** No space at the start or end of a paragraph, heading, item or cell. */
    private function trimBlocks(DOMElement $root): void
    {
        foreach ($this->elements($root, 'p', 'h1', 'h2', 'h3', 'h4', 'li', 'td', 'th', 'blockquote') as $block) {
            $first = $block->firstChild;
            $last = $block->lastChild;

            if ($first instanceof DOMText) {
                $first->data = ltrim($first->data, ' ');
            }

            if ($last instanceof DOMText) {
                $last->data = rtrim($last->data, ' ');
            }

            foreach ([$first, $last] as $node) {
                if ($node instanceof DOMText && $node->data === '' && $node->parentNode !== null) {
                    $block->removeChild($node);
                }
            }
        }
    }

    /**
     * Node by node, so there is no XML declaration. Empty elements keep a
     * closing tag (a browser reads <span/> as an opening tag), except the
     * ones that never have content.
     */
    private function serialize(DOMElement $root): string
    {
        $html = '';

        foreach ($root->childNodes as $node) {
            $html .= $root->ownerDocument->saveXML($node, LIBXML_NOEMPTYTAG);
        }

        return (string) preg_replace('#<(br|hr|img)((?:\s[^>]*)?)></\1>#', '<$1$2/>', $html);
    }

    /** Text without the control characters XML can't hold. */
    private static function clean(string $text): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0E-\x1F\x7F]/', '', $text);
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /** A source or clean node that starts a block of its own. */
    private function isBlock(?DOMNode $node): bool
    {
        if (! $node instanceof DOMElement) {
            return false;
        }

        $name = strtolower($node->nodeName);

        return in_array(self::RENAMED[$name] ?? $name, self::KEPT_BLOCKS, true) || in_array($name, self::BLOCKS, true);
    }

    private function hasBlocks(DOMNode $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($this->isBlock($child)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<DOMElement> the elements with these names, in document order */
    private function elements(DOMElement $root, string ...$names): array
    {
        $found = [];

        foreach ($root->getElementsByTagName('*') as $element) {
            if (in_array($element->nodeName, $names, true)) {
                $found[] = $element;
            }
        }

        return $found;
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function rename(DOMElement $element, string $name): void
    {
        $renamed = $element->ownerDocument->createElement($name);

        foreach (['style', 'dir'] as $attribute) {
            if ($element->hasAttribute($attribute)) {
                $renamed->setAttribute($attribute, $element->getAttribute($attribute));
            }
        }

        while ($element->firstChild !== null) {
            $renamed->appendChild($element->firstChild);
        }

        $element->parentNode?->replaceChild($renamed, $element);
    }

    private function report(string $key, string $reason): void
    {
        if ($this->warn === null || isset($this->reported[$key])) {
            return;
        }

        $this->reported[$key] = true;
        ($this->warn)(str_starts_with($key, 'image:')
            ? "easy-pdf-word: rich text: {$reason}."
            : "easy-pdf-word: rich text was cut: {$reason}.");
    }
}
