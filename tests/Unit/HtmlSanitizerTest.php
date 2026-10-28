<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    /** @var list<string> */
    private array $warnings = [];

    private function clean(string $html, ?\Closure $image = null): string
    {
        return (new HtmlSanitizer($image, function (string $message) {
            $this->warnings[] = $message;
        }))->sanitize($html);
    }

    public function test_keeps_text_list_table_and_link_tags(): void
    {
        $html = '<h1>a</h1><h2>b</h2><h3>c</h3><h4>d</h4><p>e<br>f <strong>g</strong> <b>h</b> <em>i</em> <i>j</i> <u>k</u> '
            .'<span style="color: #0f766e">l</span> <a href="https://biztech-eg.com">m</a></p><blockquote><p>n</p></blockquote><hr>'
            .'<ol start="3"><li>o</li></ol><ul><li>p</li></ul>'
            .'<table><thead><tr><th>q</th></tr></thead><tbody><tr><td colspan="2" rowspan="2">r</td></tr></tbody></table>';

        $this->assertSame(
            '<h1>a</h1><h2>b</h2><h3>c</h3><h4>d</h4><p>e<br/>f <strong>g</strong> <b>h</b> <em>i</em> <i>j</i> <u>k</u> '
            .'<span style="color: #0F766E">l</span> <a href="https://biztech-eg.com">m</a></p><blockquote><p>n</p></blockquote><hr/>'
            .'<ol start="3"><li>o</li></ol><ul><li>p</li></ul>'
            .'<table><thead><tr><th>q</th></tr></thead><tbody><tr><td colspan="2" rowspan="2">r</td></tr></tbody></table>',
            $this->clean($html),
        );
    }

    public function test_removes_scripts_and_embedded_content_with_what_is_inside(): void
    {
        foreach (['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math'] as $tag) {
            $this->assertSame('<p>before after</p>', $this->clean("<p>before <{$tag}>inside</{$tag}>after</p>"), $tag);
        }

        $this->assertSame('<p>a</p>', $this->clean('<p>a<script>document.write("<b>x</b>")</script></p>'));
    }

    public function test_unwraps_unknown_inline_tags_and_keeps_their_text(): void
    {
        $this->assertSame('<p>a b c</p>', $this->clean('<p>a <font color="red">b</font> <custom-tag>c</custom-tag></p>'));
        $this->assertSame('<p>sub and sup</p>', $this->clean('<p><sub>sub</sub> and <sup>sup</sup></p>'));
    }

    public function test_unknown_blocks_become_paragraphs_or_are_unwrapped(): void
    {
        $this->assertSame('<p>a</p><p>b</p>', $this->clean('<div>a</div><div>b</div>'));
        $this->assertSame('<p>a</p><p>b</p>', $this->clean('<section><div>a</div><p>b</p></section>'));
        $this->assertSame('<h4>five</h4><h4>six</h4>', $this->clean('<h5>five</h5><h6>six</h6>'));
        $this->assertSame('<p>x <b>y</b></p>', $this->clean('<p>x <b><div>y</div></b></p>'));
    }

    public function test_removes_every_attribute_but_the_allowed_ones(): void
    {
        $html = '<p class="lead" id="intro" onclick="steal()" onmouseover="x" data-x="1" title="t" dir="rtl" lang="ar">a</p>'
            .'<a href="https://e.com" target="_blank" rel="opener" onclick="x">b</a>'
            .'<td colspan="2">c</td><ol start="2" type="a" reversed><li value="9">d</li></ol>';

        $this->assertSame(
            '<p dir="rtl">a</p><a href="https://e.com">b</a><p>c</p><ol start="2"><li>d</li></ol>',
            $this->clean($html),
        );
    }

    public function test_keeps_only_rtl_and_ltr_directions(): void
    {
        $this->assertSame('<p dir="ltr">a</p><p>b</p>', $this->clean('<p dir="LTR">a</p><p dir="auto">b</p>'));
    }

    #[DataProvider('links')]
    public function test_keeps_http_https_and_mailto_links_only(string $href, ?string $kept): void
    {
        $expected = $kept === null ? '<p>x</p>' : '<p><a href="'.$kept.'">x</a></p>';

        $this->assertSame($expected, $this->clean('<p><a href="'.$href.'">x</a></p>'));
    }

    public static function links(): array
    {
        return [
            'https' => ['https://biztech-eg.com/a?b=1&amp;c=2', 'https://biztech-eg.com/a?b=1&amp;c=2'],
            'http, upper case' => ['HTTP://biztech-eg.com', 'HTTP://biztech-eg.com'],
            'mailto' => ['mailto:info@biztech-eg.com', 'mailto:info@biztech-eg.com'],
            'spaces around' => ['  https://e.com  ', 'https://e.com'],
            'javascript' => ['javascript:alert(1)', null],
            'javascript, mixed case' => ['JaVaScRiPt:alert(1)', null],
            'javascript with a tab entity' => ['jav&#x09;ascript:alert(1)', null],
            'javascript with a newline entity' => ['java&#10;script:alert(1)', null],
            'javascript after spaces and a control character' => [" \x01javascript:alert(1)", null],
            'javascript, double encoded' => ['javascript&amp;colon;alert(1)', null],
            'data' => ['data:text/html;base64,PHNjcmlwdD4=', null],
            'vbscript' => ['vbscript:msgbox(1)', null],
            'file' => ['file:///etc/passwd', null],
            'relative' => ['/admin', null],
            'protocol relative' => ['//evil.example', null],
            'no host' => ['https:///path', null],
        ];
    }

    #[DataProvider('styles')]
    public function test_keeps_only_the_allowed_style_values(string $style, ?string $kept, string $tag = 'p'): void
    {
        $expected = $kept === null ? "<{$tag}>x</{$tag}>" : "<{$tag} style=\"{$kept}\">x</{$tag}>";
        $html = $tag === 'td' ? "<table><tr><td style=\"{$style}\">x</td></tr></table>" : "<{$tag} style=\"{$style}\">x</{$tag}>";
        $clean = $this->clean($html);

        $this->assertSame($expected, $tag === 'td' ? substr($clean, strlen('<table><tr>'), -strlen('</tr></table>')) : $clean);
    }

    public static function styles(): array
    {
        return [
            'hex colour' => ['color: #0f766e', 'color: #0F766E'],
            'short hex colour' => ['color: #f00', 'color: #FF0000'],
            'rgb colour' => ['color: rgb(255, 0, 0)', 'color: #FF0000'],
            'hsl background' => ['background-color: hsl(0, 100%, 50%)', 'background-color: #FF0000'],
            'colour name, not yet' => ['color: red', null],
            'inherit' => ['color: inherit', null],
            'colour that adds a style' => ['color: red; background: url(https://evil.example/x)', null],
            'background shorthand' => ['background: #ff0', null],
            'url()' => ['background-color: url(https://evil.example/x)', null],
            'expression()' => ['color: expression(alert(1))', null],
            'var()' => ['color: var(--x)', null],
            'attr()' => ['color: attr(data-x)', null],
            'backslash escape' => ['color: #f\\00', null],
            'important' => ['color: #fff !important', null],
            'important with a space' => ['font-weight: bold ! important', null],
            'comment hiding a value' => ['color: /* x */ #ff0000', 'color: #FF0000'],
            'comment hiding a function' => ['color: ur/**/l(x)', null],
            'unclosed comment' => ['color: #fff /* text-align: center', 'color: #FFFFFF'],
            'text-align' => ['text-align: CENTER', 'text-align: center'],
            'text-align start' => ['text-align: start', 'text-align: start'],
            'text-align other' => ['text-align: -webkit-center', null],
            'direction' => ['direction: rtl', 'direction: rtl'],
            'direction other' => ['direction: inherit', null],
            'font-weight' => ['font-weight: 700', 'font-weight: 700'],
            'font-weight other' => ['font-weight: 900', null],
            'font-style' => ['font-style: italic', 'font-style: italic'],
            'font-style oblique' => ['font-style: oblique', null],
            'underline' => ['text-decoration: underline', 'text-decoration: underline'],
            'both decorations' => ['text-decoration: line-through underline', 'text-decoration: underline line-through'],
            'decoration with a colour' => ['text-decoration: underline red', null],
            'unknown properties' => ['position: absolute; font-family: x; font-size: 99px; margin: 0', null],
            'width on a paragraph' => ['width: 50%', null],
            'width on a cell' => ['width: 50%', 'width: 50%', 'td'],
            'width over the page' => ['width: 150%', 'width: 100%', 'td'],
            'width in mm over the page' => ['width: 900mm', 'width: 180mm', 'td'],
            'width in px' => ['width: 120px', 'width: 120px', 'td'],
            'width in em' => ['width: 10em', null, 'td'],
            'several, last wins' => ['color: #000; text-align: right; color: #111', 'color: #111111; text-align: right'],
        ];
    }

    public function test_writes_strike_through_and_highlight_as_spans(): void
    {
        $this->assertSame(
            '<p><span style="text-decoration: line-through">a</span><span style="text-decoration: line-through">b</span><span style="text-decoration: line-through">c</span></p>',
            $this->clean('<p><s>a</s><del>b</del><strike>c</strike></p>'),
        );
        $this->assertSame(
            '<p><span style="text-decoration: underline line-through">a</span></p>',
            $this->clean('<p><s style="text-decoration: underline">a</s></p>'),
        );
        $this->assertSame(
            '<p><span style="background-color: #FFFF00">a</span><span style="background-color: #FFC078">b</span></p>',
            $this->clean('<p><mark>a</mark><mark data-color="#ffc078" style="background-color: #ffc078; color: inherit">b</mark></p>'),
        );
    }

    public function test_unwraps_paragraphs_in_list_items(): void
    {
        // TipTap's output for a nested list.
        $html = '<ul><li><p style="text-align: right">first</p><p>second</p><ul><li><p>nested</p></li></ul></li><li><p>third</p></li></ul>';

        $this->assertSame(
            '<ul><li style="text-align: right">first<br/>second<ul><li>nested</li></ul></li><li>third</li></ul>',
            $this->clean($html),
        );
    }

    public function test_moves_a_table_in_a_list_item_to_after_the_list(): void
    {
        $html = '<ol><li><p>a</p><table><tr><td>1</td></tr></table></li><li><ul><li>b<table><tr><td>2</td></tr></table></li></ul></li></ol><p>after</p>';

        $this->assertSame(
            '<ol><li>a</li><li><ul><li>b</li></ul></li></ol><table><tr><td>1</td></tr></table><table><tr><td>2</td></tr></table><p>after</p>',
            $this->clean($html),
        );
    }

    public function test_writes_named_entities_only_and_no_newlines(): void
    {
        $clean = $this->clean("<p>&lt;script&gt; &#60;b&#62; &amp; \"quoted\" &copy; &nbsp;x\nnext\r\nline\tend</p>");

        $this->assertSame("<p>&lt;script&gt; &lt;b&gt; &amp; \"quoted\" © \u{A0}x next line end</p>", $clean);
        $this->assertDoesNotMatchRegularExpression('/&#|\n|\r/', $clean);
    }

    public function test_drops_white_space_between_blocks_but_keeps_it_between_words(): void
    {
        $this->assertSame(
            '<ul><li>a</li></ul><table><tr><td><b>x</b> <i>y</i></td></tr></table><p>z</p>',
            $this->clean("<ul>\n  <li> a </li>\n</ul>\n<table>\n<tr>\n <td> <b>x</b> <i>y</i> </td>\n</tr>\n</table>\n<p>z</p>"),
        );
    }

    public function test_never_writes_an_empty_inline_tag_a_browser_would_leave_open(): void
    {
        $this->assertSame('<p><b></b><span style="color: #000000"></span>a</p><p></p>', $this->clean('<p><b></b><span style="color:#000"></span><span></span>a</p><p></p>'));
        $this->assertStringNotContainsString('/>', str_replace(['<br/>', '<hr/>'], '', $this->clean('<p><b></b><a href="https://e.com"></a></p><p></p><br><hr>')));
    }

    public function test_images_go_through_the_image_check(): void
    {
        $seen = [];
        $html = $this->clean('<p><img src="logo.png" width="100" onerror="x"><img src="/etc/passwd"></p>', function (string $src) use (&$seen) {
            $seen[] = $src;

            return $src === 'logo.png' ? 'data:image/png;base64,AAAA' : null;
        });

        $this->assertSame(['logo.png', '/etc/passwd'], $seen);
        $this->assertSame('<p><img src="data:image/png;base64,AAAA" width="100"/></p>', $html);
        $this->assertSame(['easy-pdf-word: rich text: an image was dropped [/etc/passwd].'], $this->warnings);
    }

    public function test_keeps_web_and_raster_data_images_by_default(): void
    {
        $this->assertSame(
            '<p><img src="https://e.com/a.png"/><img src="data:image/png;base64,AAAA"/><img src="data:image/webp;base64,AAAA"/></p>',
            $this->clean('<p><img src="https://e.com/a.png"><img src="data:image/png;base64,AAAA"><img src="data:image/webp;base64,AAAA">'
                .'<img src="data:image/svg+xml;base64,PHN2Zz4="><img src="javascript:x"><img src="file:///etc/passwd"><img src="logo.png"><img></p>'),
        );
    }

    public function test_caps_image_sizes_at_the_text_width(): void
    {
        $this->assertSame(
            '<p><img src="https://e.com/a.png" width="680" height="340"/><img src="https://e.com/b.png" height="680"/><img src="https://e.com/c.png" width="20"/></p>',
            $this->clean('<p><img src="https://e.com/a.png" width="2000" height="1000"><img src="https://e.com/b.png" height="5000"><img src="https://e.com/c.png" width="20px" height="50%"></p>'),
        );

        $narrow = new HtmlSanitizer(contentWidth: 50.8);

        $this->assertSame('<p><img src="https://e.com/a.png" width="192"/></p>', $narrow->sanitize('<p><img src="https://e.com/a.png" width="500"></p>'));
    }

    public function test_cuts_input_over_the_size_limit(): void
    {
        $clean = $this->clean('<p>'.str_repeat('ب', HtmlSanitizer::MAX_BYTES).'</p>');

        // "<p>" and the cut leave room for one letter fewer; no letter is cut in half.
        $this->assertSame(str_repeat('ب', intdiv(HtmlSanitizer::MAX_BYTES - 3, 2)), strip_tags($clean));
        $this->assertTrue(mb_check_encoding($clean, 'UTF-8'));
        $this->assertSame(['easy-pdf-word: rich text was cut: it is over 256 KB.'], $this->warnings);
    }

    public function test_flattens_elements_nested_too_deep(): void
    {
        $html = str_repeat('<span style="color: #000">', 30).'deep<script>x</script>'.str_repeat('</span>', 30);
        $clean = $this->clean($html);

        $this->assertSame(HtmlSanitizer::MAX_DEPTH, substr_count($clean, '<span'));
        $this->assertStringContainsString('deep', $clean);
        $this->assertStringNotContainsString('x', strip_tags($clean));
        $this->assertSame(['easy-pdf-word: rich text was cut: it is nested more than 20 levels deep.'], $this->warnings);
    }

    public function test_cuts_tables_over_the_row_and_column_limits(): void
    {
        $row = '<tr>'.str_repeat('<td>c</td>', 25).'</tr>';
        $clean = $this->clean('<table><thead>'.$row.'</thead><tbody>'.str_repeat($row, 250).'</tbody></table>');

        $this->assertSame(HtmlSanitizer::MAX_TABLE_ROWS, substr_count($clean, '<tr>'));
        $this->assertSame(HtmlSanitizer::MAX_TABLE_ROWS * HtmlSanitizer::MAX_TABLE_COLUMNS, substr_count($clean, '<td>'));
        $this->assertSame([
            'easy-pdf-word: rich text was cut: a table has more than 200 rows.',
            'easy-pdf-word: rich text was cut: a table has more than 20 columns.',
        ], $this->warnings);
    }

    public function test_limits_spans(): void
    {
        $clean = $this->clean('<table><tr><td>a</td><td colspan="99" rowspan="50">b</td><td colspan="0">c</td><td colspan="x">d</td></tr></table>');

        $this->assertSame('<table><tr><td>a</td><td colspan="19" rowspan="20">b</td></tr></table>', $clean);
        $this->assertSame([
            'easy-pdf-word: rich text was cut: a cell spans more than 20 rows or columns.',
            'easy-pdf-word: rich text was cut: a table has more than 20 columns.',
        ], $this->warnings);
    }

    public function test_repairs_list_items_cells_and_rows_out_of_place(): void
    {
        $this->assertSame('<p>item</p><p>cell</p>', $this->clean('<li>item</li><td>cell</td>'));
        $this->assertSame('<ul><li>a</li></ul>', $this->clean('<ul>stray<li>a</li><p>stray</p></ul>'));
    }

    #[DataProvider('malformed')]
    public function test_malformed_html_never_throws(string $html): void
    {
        $clean = $this->clean($html);

        $this->assertIsString($clean);
        $this->assertTrue(mb_check_encoding($clean, 'UTF-8'));
        $this->assertNotFalse(@simplexml_load_string('<root>'.$clean.'</root>'), $clean);
    }

    public static function malformed(): array
    {
        return [
            'empty' => [''],
            'white space' => ["  \n "],
            'unclosed tags' => ['<p><b>bold <i>both</p><ul><li>a<li>b'],
            'stray closing tags' => ['</p></div>text</b>'],
            'broken attributes' => ['<p style="color:#f00 dir=rtl>x</p><a href=>y</a>'],
            'invalid UTF-8' => ["<p>bad \xC3( byte</p>"],
            'control characters' => ["<p>a\x00b\x07c\x1Bd</p>"],
            'a whole page' => ['<!DOCTYPE html><html><head><title>t</title><style>p{}</style></head><body><p>x</p></body></html>'],
            'comments and CDATA' => ['<p>a<!-- c --><![CDATA[d]]></p><?php echo 1; ?>'],
            'only a script' => ['<script>alert(1)</script>'],
            'a table in a table' => ['<table><tr><td><table><tr><td>x</td></tr></table></td></tr></table>'],
            'text in a table' => ['<table>loose<tr>text<td>x</td></tr></table>'],
        ];
    }
}
