<?php

namespace BiztechEG\EasyPdfWord\Word;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Link;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\NumberFormat;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Numbering;
use PhpOffice\PhpWord\Style\Paragraph;
use ReflectionProperty;

/**
 * Sanitised rich text (the XHTML of Support\HtmlSanitizer) written into a
 * Word container by PhpWord's HTML parser, with what the parser needs
 * before and after it:
 *
 * - before: every paragraph, heading, list item and cell gets the
 *   document's direction (the parser maps alignment from it), images and
 *   quotes become markers, rowspans become continuation cells, and
 *   underline and strike-through are moved onto the text they cover;
 * - after: Arabic runs are marked right to left, tables are laid out in
 *   the document's direction, markers become images and indents, list
 *   paragraphs follow the direction and runs get the document's digits.
 *
 * @internal
 */
final class WordHtml
{
    private const IMAGE = "\u{E000}";

    private const IMAGE_END = "\u{E001}";

    private const QUOTE = "\u{E002}";

    /** Twips a quote is indented by, per level. */
    private const QUOTE_INDENT = 567;

    private const BLOCKS = ['p', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'table', 'blockquote', 'hr'];

    private const DIRECTED = ['p', 'h1', 'h2', 'h3', 'h4', 'li', 'td', 'th'];

    /** @var array<int, array{src: string, width: ?float}> */
    private array $images = [];

    /** @var array<int, array<int, array<int, string>>> vMerge by table, row and cell */
    private array $merges = [];

    /** @var array<int, string> each table's CSS width */
    private array $widths = [];

    private int $table = 0;

    /** @var list<array<int, string>> each top-level list's kind ("ul" or "ol") by depth */
    private array $lists = [];

    /**
     * @param  Closure(string): string  $text  the document's digits for a run's text
     * @param  Closure(Font, string, bool): void  $font  marks a run's font for its text; true for body text (not a heading)
     * @param  array{border: string, header_background: string}  $grid  the free tables' look, #hex colours
     * @param  float  $maxWidth  points: the widest an image may be
     */
    public function __construct(
        private readonly bool $rtl,
        private readonly Closure $text,
        private readonly Closure $font,
        private readonly array $grid,
        private readonly float $maxWidth,
    ) {}

    public function write(AbstractContainer $container, string $xhtml): void
    {
        $before = count($container->getElements());
        $html = $this->prepare($xhtml);
        // The parser names each top-level list's numbering listStyle_N, counting on from here.
        $firstList = property_exists(Html::class, 'listIndex') ? (new ReflectionProperty(Html::class, 'listIndex'))->getValue() : null;

        Html::addHtml($container, $html, false, true);

        $this->finish($container, $before);

        if (is_int($firstList)) {
            $this->numbering($firstList);
        }
    }

    private function prepare(string $xhtml): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML('<?xml version="1.0" encoding="UTF-8"?><root>'.$xhtml.'</root>', LIBXML_NONET);
        $root = $dom->documentElement;
        $xpath = new DOMXPath($dom);

        foreach (iterator_to_array($dom->getElementsByTagName('img')) as $n => $img) {
            $width = (float) $img->getAttribute('width');
            $this->images[$n] = ['src' => $img->getAttribute('src'), 'width' => $width > 0 ? $width * 0.75 : null];
            $img->parentNode->replaceChild($dom->createTextNode(self::IMAGE.$n.self::IMAGE_END), $img);
        }

        // Text beside blocks would become a paragraph of its own per piece.
        $this->wrapInline($root, true);

        foreach ($xpath->query('//blockquote | //td | //th') as $container) {
            $this->wrapInline($container, false);
        }

        foreach ($xpath->query('//blockquote//p') as $p) {
            $p->insertBefore($dom->createTextNode(str_repeat(self::QUOTE, $xpath->query('ancestor::blockquote', $p)->length)), $p->firstChild);
        }

        foreach ($xpath->query('//*[@style]') as $element) {
            $this->decorations($element);
        }

        foreach ($xpath->query('//'.implode(' | //', self::DIRECTED)) as $element) {
            $this->direction($element);
        }

        foreach ($xpath->query('//table') as $table) {
            $this->grid($table, $xpath);
        }

        foreach ($xpath->query('//ul[not(ancestor::li)] | //ol[not(ancestor::li)]') as $list) {
            $kinds = [];

            foreach ($xpath->query('descendant-or-self::ul | descendant-or-self::ol', $list) as $nested) {
                $kinds[$xpath->query('ancestor::li', $nested)->length] ??= $nested->nodeName;
            }

            $this->lists[] = $kinds;
        }

        $html = '';

        foreach ($root->childNodes as $node) {
            $html .= $dom->saveXML($node);
        }

        return $html;
    }

    /** Runs of inline nodes in a container that also holds blocks become paragraphs. */
    private function wrapInline(DOMElement $container, bool $always): void
    {
        $children = iterator_to_array($container->childNodes);
        $hasBlocks = array_filter($children, fn (DOMNode $node) => in_array($node->nodeName, self::BLOCKS, true)) !== [];

        if (! $always && ! $hasBlocks) {
            return;
        }

        $run = [];

        foreach ([...$children, null] as $node) {
            if ($node !== null && ! in_array($node->nodeName, self::BLOCKS, true)) {
                $run[] = $node;

                continue;
            }

            if (trim(implode('', array_map(fn (DOMNode $n) => $n->textContent, $run))) !== '') {
                $p = $container->ownerDocument->createElement('p');
                $container->insertBefore($p, $run[0]);

                foreach ($run as $inline) {
                    $p->appendChild($inline);
                }
            } else {
                foreach ($run as $inline) {
                    $container->removeChild($inline);
                }
            }

            $run = [];
        }
    }

    /**
     * The parser reads text-decoration only from a text's own parent and
     * not "underline line-through" together, so each piece of text gets
     * its own.
     */
    private function decorations(DOMElement $element): void
    {
        $style = self::style($element);

        if (! isset($style['text-decoration'])) {
            return;
        }

        $decoration = $style['text-decoration'];
        unset($style['text-decoration']);
        self::setStyle($element, $style);

        if ($decoration === 'none') {
            return;
        }

        $texts = [];

        foreach ((new DOMXPath($element->ownerDocument))->query('.//text()', $element) as $text) {
            $texts[] = $text;
        }

        foreach ($texts as $text) {
            $wrapper = $text;

            if (str_contains($decoration, 'line-through')) {
                $span = $element->ownerDocument->createElement('span');
                $span->setAttribute('style', 'text-decoration: line-through');
                $text->parentNode->replaceChild($span, $text);
                $span->appendChild($text);
                $wrapper = $span;
            }

            if (str_contains($decoration, 'underline')) {
                $u = $element->ownerDocument->createElement('u');
                $wrapper->parentNode->replaceChild($u, $wrapper);
                $u->appendChild($wrapper);
            }
        }
    }

    /**
     * The parser sets bidi and maps alignment from the element's own
     * declarations, so the direction is declared, and start and end are
     * given as the side they mean in it.
     */
    private function direction(DOMElement $element): void
    {
        $style = self::style($element);
        $dir = $element->getAttribute('dir');
        $direction = $style['direction'] ?? ($dir !== '' ? $dir : ($this->rtl ? 'rtl' : 'ltr'));
        $style['direction'] = $direction;

        if (in_array($style['text-align'] ?? null, ['start', 'end'], true)) {
            $style['text-align'] = ($style['text-align'] === 'start') === ($direction === 'rtl') ? 'right' : 'left';
        }

        self::setStyle($element, $style);
    }

    /**
     * The builder's table look with a grid, and rowspans as continuation
     * cells: the parser reads colspan only, and a row can only append.
     */
    private function grid(DOMElement $table, DOMXPath $xpath): void
    {
        // Its cells would inherit the width, so the table gets it afterwards.
        $style = self::style($table);
        $this->widths[] = $style['width'] ?? '100%';
        unset($style['width']);
        self::setStyle($table, $style);

        $rows = iterator_to_array($xpath->query('./tr | ./thead/tr | ./tbody/tr', $table));
        $pending = [];

        foreach ($rows as $r => $row) {
            $col = 0;

            foreach (iterator_to_array($xpath->query('./td | ./th', $row)) as $cell) {
                $col = $this->continuations($row, $cell, $col, $pending, $r);
                $span = max(1, (int) $cell->getAttribute('colspan'));
                $rowspan = (int) $cell->getAttribute('rowspan');
                $cell->removeAttribute('rowspan');

                if ($rowspan > 1) {
                    $cell->setAttribute('data-vmerge', 'restart');
                    $pending[$col] = ['until' => $r + $rowspan - 1, 'span' => $span];
                }

                $col += $span;
            }

            $this->continuations($row, null, $col, $pending, $r);
        }

        $merges = [];

        foreach ($rows as $r => $row) {
            foreach (iterator_to_array($xpath->query('./td | ./th', $row)) as $i => $cell) {
                $look = ['padding' => '3pt 5pt', 'border' => '1px '.$this->grid['border'].' solid', 'vertical-align' => 'top'];

                if ($cell->nodeName === 'th') {
                    $look += ['font-weight' => 'bold', 'color' => '#FFFFFF', 'background-color' => $this->grid['header_background']];
                }

                self::setStyle($cell, $look + self::style($cell));

                if ($cell->hasAttribute('data-vmerge')) {
                    $merges[$r][$i] = $cell->getAttribute('data-vmerge');
                    $cell->removeAttribute('data-vmerge');
                }
            }
        }

        $this->merges[] = $merges;
    }

    /**
     * Empty cells where cells above span into this row: before $cell, at
     * the columns up to its own, or at the end of the row when $cell is null.
     *
     * @return int the column $cell starts at
     */
    private function continuations(DOMElement $row, ?DOMElement $cell, int $col, array &$pending, int $r): int
    {
        while (isset($pending[$col]) || ($cell === null && array_filter(array_keys($pending), fn ($c) => $c >= $col) !== [])) {
            if (! isset($pending[$col])) {
                $col++;

                continue;
            }

            ['until' => $until, 'span' => $span] = $pending[$col];

            if ($until < $r) {
                unset($pending[$col]);

                continue;
            }

            $continue = $row->ownerDocument->createElement('td');
            $continue->setAttribute('data-vmerge', 'continue');

            if ($span > 1) {
                $continue->setAttribute('colspan', (string) $span);
            }

            $cell === null ? $row->appendChild($continue) : $row->insertBefore($continue, $cell);

            if ($until === $r) {
                unset($pending[$col]);
            }

            $col += $span;
        }

        return $col;
    }

    private function finish(AbstractContainer $container, int $from): void
    {
        // Images first: the markers' numbers must not become Arabic digits.
        foreach ($this->containers($container, $from) as $parent) {
            $this->replaceImages($parent, $parent === $container ? $from : 0);
        }

        foreach (array_slice($container->getElements(), $from) as $element) {
            $this->element($element, false);
        }
    }

    private function element(AbstractElement $element, bool $heading): void
    {
        if ($element instanceof Table) {
            if ($this->rtl) {
                $element->getStyle()->setBidiVisual(true);
            }

            $width = $this->widths[$this->table] ?? '100%';
            str_ends_with($width, '%')
                ? $element->getStyle()->setUnit(TblWidth::PERCENT)->setWidth((int) round((float) $width * 50))
                : $element->getStyle()->setUnit(TblWidth::TWIP)->setWidth((int) round(Converter::cssToTwip($width)));
            $merges = $this->merges[$this->table++] ?? [];

            foreach ($element->getRows() as $r => $row) {
                foreach ($row->getCells() as $i => $cell) {
                    if (isset($merges[$r][$i])) {
                        $cell->getStyle()->setVMerge($merges[$r][$i]);
                    }

                    foreach ($cell->getElements() as $child) {
                        $this->element($child, false);
                    }
                }
            }

            return;
        }

        if ($element instanceof TextRun) {
            $paragraph = $element->getParagraphStyle();

            if (! $paragraph instanceof Paragraph) {
                $paragraph = $element->setParagraphStyle(is_string($paragraph) ? ['styleName' => $paragraph] : null);
            }

            // The parser gives list items their list's style, not their own.
            if ($element instanceof ListItemRun && $this->rtl) {
                $paragraph->setBidi(true);
            }

            $this->quote($element, $paragraph);
            $heading = str_starts_with((string) $paragraph->getStyleName(), 'Heading');

            foreach ($element->getElements() as $child) {
                $this->element($child, $heading);
            }

            return;
        }

        if ($element instanceof Text) {
            $text = ($this->text)((string) $element->getText());
            $element->setText($text);
            $font = $element->getFontStyle();

            if ($font instanceof Font) {
                ($this->font)($font, $text, ! $heading);
            }

            return;
        }

        if ($element instanceof Link) {
            $font = $element->getFontStyle();

            if ($font instanceof Font) {
                ($this->font)($font, (string) $element->getText(), ! $heading);
            }

            return;
        }

        if ($element instanceof AbstractContainer) {
            foreach ($element->getElements() as $child) {
                $this->element($child, $heading);
            }
        }
    }

    /**
     * The parser gives a whole list, nested lists included, the numbering
     * of its top level: a bulleted list inside a numbered one would come
     * out lettered. Those levels are rewritten to the nested list's kind.
     */
    private function numbering(int $firstList): void
    {
        foreach ($this->lists as $k => $kinds) {
            $style = Style::getStyle('listStyle_'.($firstList + $k));

            if (! $style instanceof Numbering) {
                continue;
            }

            $levels = $style->getLevels();

            foreach ($kinds as $depth => $kind) {
                $level = $levels[$depth] ?? null;

                if ($depth === 0 || $level === null || ($kind === 'ul') === ($level->getFormat() === NumberFormat::BULLET)) {
                    continue;
                }

                $kind === 'ul'
                    ? $level->setFormat(NumberFormat::BULLET)->setText($depth % 2 === 1 ? '◦' : '•')
                    : $level->setFormat(NumberFormat::DECIMAL)->setText('%'.($depth + 1).'.');
                $level->setFont(null)->setHint(null);
            }
        }
    }

    /** A quoted paragraph starts with a marker per level; it becomes a start-side indent. */
    private function quote(TextRun $run, Paragraph $paragraph): void
    {
        $first = $run->getElements()[0] ?? null;

        if (! $first instanceof Text || ! str_starts_with((string) $first->getText(), self::QUOTE)) {
            return;
        }

        $text = (string) $first->getText();
        $levels = strspn($text, self::QUOTE) / strlen(self::QUOTE);
        $first->setText(substr($text, $levels * strlen(self::QUOTE)));
        // In a bidi paragraph, Word reads the left indent as the start side.
        $paragraph->setIndentation(['left' => self::QUOTE_INDENT * $levels]);
    }

    /** @return AbstractContainer[] the containers the new elements put text in, deepest first */
    private function containers(AbstractContainer $container, int $from): array
    {
        $found = [];

        foreach (array_slice($container->getElements(), $from) as $element) {
            if ($element instanceof Table) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $found = [...$found, ...$this->containers($cell, 0)];
                    }
                }
            } elseif ($element instanceof AbstractContainer) {
                $found = [...$found, ...$this->containers($element, 0)];
            }
        }

        $found[] = $container;

        return $found;
    }

    /**
     * Image markers in a container's texts become images at the same
     * place, through WordImage, as builder images: SVG is left out,
     * WebP and BMP become PNG, and nothing is written to the temp folder.
     */
    private function replaceImages(AbstractContainer $container, int $from): void
    {
        $elements = $container->getElements();
        $changed = false;
        $result = array_slice($elements, 0, $from);

        foreach (array_slice($elements, $from) as $element) {
            if (! $element instanceof Text || ! str_contains((string) $element->getText(), self::IMAGE)) {
                $result[] = $element;

                continue;
            }

            $changed = true;
            $parts = preg_split('/'.self::IMAGE.'(\d+)'.self::IMAGE_END.'/u', (string) $element->getText(), -1, PREG_SPLIT_DELIM_CAPTURE);

            foreach ($parts as $i => $part) {
                $count = count($container->getElements());

                if ($i % 2 === 0) {
                    if ($part !== '') {
                        $font = $element->getFontStyle();
                        $container->addText($part, $font instanceof Font ? clone $font : $font, $element->getParagraphStyle());
                    }
                } elseif (($bytes = WordImage::load($this->images[(int) $part]['src'] ?? '')) !== null) {
                    $width = min($this->images[(int) $part]['width'] ?? $this->maxWidth, $this->maxWidth);
                    $container->addImage($bytes, ['width' => round($width)]);
                }

                if (count($container->getElements()) > $count) {
                    $added = $container->getElements();
                    $result[] = end($added);
                }
            }
        }

        if ($changed) {
            (new ReflectionProperty(AbstractContainer::class, 'elements'))->setValue($container, $result);
        }
    }

    /** @return array<string, string> */
    private static function style(DOMElement $element): array
    {
        $style = [];

        foreach (explode(';', $element->getAttribute('style')) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, null);

            if ($value !== null && trim($property) !== '') {
                $style[strtolower(trim($property))] = trim($value);
            }
        }

        return $style;
    }

    /** @param  array<string, string>  $style */
    private static function setStyle(DOMElement $element, array $style): void
    {
        $style === []
            ? $element->removeAttribute('style')
            : $element->setAttribute('style', implode('; ', array_map(fn ($p, $v) => $p.': '.$v, array_keys($style), $style)));
    }
}
