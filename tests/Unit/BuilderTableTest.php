<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BadMethodCallException;
use BiztechEG\EasyPdfWord\Builder\DocumentBuilder as B;
use BiztechEG\EasyPdfWord\Document;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Builder tables: row objects, row styles, total rows and rows kept with the
 * next one, in PDF (the HTML) and in Word (document.xml).
 */
class BuilderTableTest extends TestCase
{
    private static function table(array $rows, array $options = []): Document
    {
        return EasyPdfWord::create([
            'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => sys_get_temp_dir().'/easy-pdf-word-tests/mpdf']]],
        ])->make()->locale('ar')->table($rows, $options);
    }

    /** @return string[] the HTML of each row */
    private static function htmlRows(Document $document): array
    {
        preg_match_all('/<tr\b.*?<\/tr>/s', $document->toHtml(), $matches);

        return $matches[0];
    }

    /** @return string[] the XML of each row */
    private static function wordRows(Document $document): array
    {
        $file = tempnam(sys_get_temp_dir(), 'easy-docx-test');
        file_put_contents($file, $document->word()->content());
        $zip = new ZipArchive;
        $zip->open($file);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($file);

        preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $xml, $matches);

        return $matches[0];
    }

    public function test_row_builds_a_row_object_with_its_options_only(): void
    {
        $this->assertSame(
            ['cells' => ['a', 'b'], 'style' => ['bold' => true], 'total' => true],
            B::row(['x' => 'a', 'y' => 'b'], ['style' => ['bold' => true], 'total' => true, 'colspan' => 2]),
        );
    }

    public function test_reads_row_objects_and_plain_rows(): void
    {
        [$cells, $options] = B::rowParts(B::row(['a'], ['style' => ['bold' => true, 'text' => 'x', 'colspan' => 3], 'keep_with_next' => true]));
        $this->assertSame(['a'], $cells);
        $this->assertSame(['style' => ['bold' => true], 'total' => false, 'keep_with_next' => true], $options);

        $this->assertSame(['1', 'b'], B::rowParts(['1', 'b'])[0]);
        $this->assertSame(['Ali', '30'], B::rowParts(['name' => 'Ali', 'age' => '30'])[0]);
    }

    public function test_a_record_with_a_cells_column_is_read_as_before(): void
    {
        $this->assertSame(['3', 'Room A'], B::rowParts(['cells' => '3', 'name' => 'Room A'])[0]);
        $this->assertSame(['3'], B::rowParts(['cells' => '3'])[0]);
        $this->assertSame([['a', 'b'], 'Room A'], B::rowParts(['cells' => ['a', 'b'], 'name' => 'Room A'])[0]);
        $this->assertSame([['x' => 'a']], B::rowParts(['cells' => ['x' => 'a']])[0]);

        $rows = self::htmlRows(self::table([['cells' => '3', 'name' => 'Room A']]));
        $this->assertStringContainsString('>3</td>', $rows[0]);
        $this->assertStringContainsString('>Room A</td>', $rows[0]);
    }

    public function test_plain_tables_render_as_before(): void
    {
        $rows = self::htmlRows(self::table([['البند', 'القيمة'], ['أ', '10'], ['الإجمالي', '10']], ['header' => true, 'footer' => true]));

        $this->assertSame('<tr><td style="padding: 2mm; vertical-align: top; border-bottom: 1px solid #E5E7EB;text-align: right; font-weight: bold;">الإجمالي</td>'
            .'<td style="padding: 2mm; vertical-align: top; border-bottom: 1px solid #E5E7EB;text-align: right; font-weight: bold;">10</td></tr>', $rows[2]);
        $this->assertStringStartsWith('<tr><th ', $rows[0]);
        $this->assertStringNotContainsString('border-top', implode('', $rows));
        $this->assertStringNotContainsString('<tr style', implode('', $rows));
    }

    public function test_pdf_row_style_applies_to_every_cell_and_the_cell_style_wins(): void
    {
        $rows = self::htmlRows(self::table([
            ['البند', 'القيمة'],
            B::row(['أ', ['text' => '10', 'color' => '#DC2626', 'align' => 'end']], ['style' => ['italic' => true, 'color' => '#64748B', 'background' => '#F1F5F9', 'align' => 'center']]),
        ], ['header' => true, 'striped' => '#FAFAFA']));

        preg_match_all('/<td style="([^"]*)"/', $rows[1], $cells);
        $this->assertStringContainsString('font-style: italic; color: #64748B; background-color: #F1F5F9', $cells[1][0]);
        $this->assertStringContainsString('text-align: center', $cells[1][0]);
        $this->assertStringContainsString('color: #DC2626', $cells[1][1]);
        $this->assertStringContainsString('text-align: left', $cells[1][1]);
        $this->assertStringContainsString('background-color: #F1F5F9', $cells[1][1]);
        $this->assertStringNotContainsString('#FAFAFA', $rows[1]);
    }

    public function test_pdf_row_style_wins_over_the_header_and_ignores_unknown_keys(): void
    {
        $rows = self::htmlRows(self::table([
            B::row(['البند'], ['style' => ['background' => '#111827', 'border' => '#000000', 'colspan' => 4, 'text' => 'x']]),
            ['أ'],
        ], ['header' => true]));

        $this->assertStringContainsString('background-color: #111827', $rows[0]);
        $this->assertStringContainsString('border: 1px solid #000000;', $rows[0]);
        $this->assertStringNotContainsString('colspan', $rows[0]);
        $this->assertStringContainsString('>البند</th>', $rows[0]);
    }

    public function test_pdf_marks_several_total_rows(): void
    {
        $rows = self::htmlRows(self::table([
            ['أ', '10'],
            B::row(['المجموع الفرعي', '10'], ['total' => true]),
            ['ب', '20'],
            B::row(['الإجمالي', '30'], ['total' => true, 'style' => ['border' => '#000000']]),
        ]));

        $this->assertStringNotContainsString('border-top', $rows[0]);
        $this->assertSame(2, substr_count($rows[1], 'border-top: 1pt solid #E5E7EB;'));
        $this->assertSame(2, substr_count($rows[1], 'font-weight: bold'));
        $this->assertStringNotContainsString('font-weight', $rows[2]);
        // A box around the cells replaces the line above.
        $this->assertStringNotContainsString('border-top', $rows[3]);
        $this->assertSame(2, substr_count($rows[3], 'font-weight: bold'));
    }

    public function test_pdf_keeps_a_row_with_the_next_one(): void
    {
        $rows = self::htmlRows(self::table([
            B::row([['text' => 'أعمال مدنية', 'colspan' => 2]], ['keep_with_next' => true]),
            ['حفر', '10'],
        ]));

        $this->assertStringStartsWith('<tr style="page-break-after: avoid;"><td colspan="2"', $rows[0]);
        $this->assertStringStartsWith('<tr><td', $rows[1]);
    }

    public function test_mpdf_renders_row_objects(): void
    {
        $rows = [['#', 'البند', 'القيمة']];

        foreach (range(1, 3) as $section) {
            $rows[] = B::row([['text' => 'قسم '.$section, 'colspan' => 3]], ['style' => ['bold' => true, 'background' => '#F1F5F9'], 'keep_with_next' => true]);

            foreach (range(1, 20) as $line) {
                $rows[] = [(string) $line, 'بند '.$line, '10.00'];
            }

            $rows[] = B::row([['text' => 'المجموع', 'colspan' => 2], '200.00'], ['total' => true]);
        }

        $this->assertStringStartsWith('%PDF', self::table($rows, ['header' => true])->pdf()->content());
    }

    public function test_word_row_style_applies_to_every_cell_and_the_cell_style_wins(): void
    {
        $rows = self::wordRows(self::table([
            ['البند', 'القيمة'],
            B::row(['أ', ['text' => '10', 'color' => '#DC2626', 'background' => '#FEF2F2']], ['style' => ['italic' => true, 'color' => '#64748B', 'background' => '#F1F5F9']]),
        ], ['header' => true, 'striped' => '#FAFAFA']));

        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $rows[1], $cells);
        $this->assertStringContainsString('w:fill="F1F5F9"', $cells[0][0]);
        $this->assertStringContainsString('<w:i w:val="1"/>', $cells[0][0]);
        $this->assertStringContainsString('<w:color w:val="64748B"/>', $cells[0][0]);
        $this->assertStringContainsString('w:fill="FEF2F2"', $cells[0][1]);
        $this->assertStringContainsString('<w:color w:val="DC2626"/>', $cells[0][1]);
        $this->assertStringContainsString('<w:i w:val="1"/>', $cells[0][1]);
        $this->assertStringNotContainsString('FAFAFA', $rows[1]);
    }

    public function test_word_marks_several_total_rows(): void
    {
        $rows = self::wordRows(self::table([
            ['أ', '10'],
            B::row(['المجموع الفرعي', '10'], ['total' => true]),
            ['ب', '20'],
            B::row(['الإجمالي', '30'], ['total' => true]),
        ], ['footer' => true]));

        $this->assertStringNotContainsString('<w:top ', $rows[0]);
        $this->assertStringNotContainsString('<w:b w:val="1"/>', $rows[0]);

        foreach ([$rows[1], $rows[3]] as $row) {
            $this->assertSame(2, substr_count($row, '<w:top w:val="single" w:sz="8" w:color="E5E7EB"/>'));
            $this->assertSame(2, substr_count($row, '<w:b w:val="1"/>'));
        }

        $this->assertStringNotContainsString('<w:b w:val="1"/>', $rows[2]);
    }

    public function test_word_footer_stays_bold_without_a_line_above(): void
    {
        $rows = self::wordRows(self::table([['أ', '10'], ['الإجمالي', '10']], ['footer' => true]));

        $this->assertStringContainsString('<w:b w:val="1"/>', $rows[1]);
        $this->assertStringNotContainsString('<w:top ', $rows[1]);
    }

    public function test_word_keeps_a_row_with_the_next_one(): void
    {
        $rows = self::wordRows(self::table([
            B::row([['text' => 'أعمال مدنية', 'colspan' => 2]], ['keep_with_next' => true]),
            ['حفر', '10'],
        ]));

        $this->assertStringContainsString('<w:keepNext w:val="1"/>', $rows[0]);
        $this->assertStringContainsString('<w:gridSpan w:val="2"/>', $rows[0]);
        $this->assertStringNotContainsString('<w:keepNext w:val="1"/>', $rows[1]);
    }

    public function test_grid_places_cells_and_leaves_continuations_below_a_rowspan(): void
    {
        $grid = B::grid([
            [['text' => 'A', 'rowspan' => 2], 'B', 'C'],
            ['b2', 'c2'],
            ['a3', ['text' => 'X', 'rowspan' => 9, 'colspan' => 2]],
            ['a4'],
        ]);
        $read = fn (array $row) => array_map(fn ($e) => ($e['continue'] ? '~' : '').$e['cell']['text'].'@'.$e['col'].'x'.$e['span'].'r'.$e['rowspan'], $row);

        $this->assertSame(['A@0x1r2', 'B@1x1r1', 'C@2x1r1'], $read($grid[0]));
        $this->assertSame(['~A@0x1r1', 'b2@1x1r1', 'c2@2x1r1'], $read($grid[1]));
        // A rowspan stops at the last row.
        $this->assertSame(['a3@0x1r1', 'X@1x2r2'], $read($grid[2]));
        $this->assertSame(['a4@0x1r1', '~X@1x2r1'], $read($grid[3]));
    }

    public function test_borders_read_true_false_and_their_names(): void
    {
        $this->assertSame('rows', B::borders(true));
        $this->assertSame('rows', B::borders('rows'));
        $this->assertSame('rows', B::borders('dotted'));
        $this->assertSame('grid', B::borders('grid'));
        $this->assertSame('none', B::borders(false));
        $this->assertSame('none', B::borders('none'));
        $this->assertSame('none', B::borders(0));
    }

    public function test_pdf_borders_grid_rows_and_none(): void
    {
        $grid = self::htmlRows(self::table([['أ', 'ب']], ['borders' => 'grid', 'border_width' => 1.5, 'border_color' => '#123456']))[0];
        $rows = self::htmlRows(self::table([['أ', 'ب']], ['borders' => true]))[0];
        $none = self::htmlRows(self::table([['أ', 'ب']], ['borders' => 'none']))[0];
        $false = self::htmlRows(self::table([['أ', 'ب']], ['borders' => false]))[0];

        $this->assertSame(2, substr_count($grid, 'border: 1.5pt solid #123456;'));
        $this->assertSame(2, substr_count($rows, 'border-bottom: 1px solid #E5E7EB;'));
        $this->assertStringNotContainsString('border', $none);
        $this->assertSame($none, $false);
    }

    public function test_pdf_padding_and_vertical_alignment(): void
    {
        $row = self::htmlRows(self::table([[['text' => 'أ', 'padding' => 1, 'valign' => 'bottom'], 'ب', 'ج']], ['padding' => 4, 'valign' => 'middle']))[0];
        $plain = self::htmlRows(self::table([['أ']]))[0];

        $this->assertStringContainsString('padding: 1mm; vertical-align: bottom;', $row);
        $this->assertSame(2, substr_count($row, 'padding: 4mm; vertical-align: middle;'));
        $this->assertStringContainsString('padding: 2mm; vertical-align: top;', $plain);
    }

    public function test_pdf_keeps_a_table_together(): void
    {
        $html = self::table([['أ'], ['ب']], ['keep_together' => true])->toHtml();

        $this->assertStringContainsString('<table style="width: 100%; border-collapse: collapse; margin-bottom: 3mm; page-break-inside: avoid; break-inside: avoid;">', $html);
        $this->assertStringNotContainsString('page-break-inside', self::table([['أ']])->toHtml());
    }

    public function test_pdf_rowspan_leaves_out_the_covered_cells(): void
    {
        $rows = self::htmlRows(self::table([
            [['text' => 'أعمال مدنية', 'rowspan' => 2], 'حفر', '10'],
            ['ردم', '20'],
        ], ['columns' => [50, 30, 20]]));

        $this->assertStringContainsString('<td rowspan="2" style="padding: 2mm; vertical-align: top; border-bottom: 1px solid #E5E7EB;width: 50%;', $rows[0]);
        $this->assertSame(2, substr_count($rows[1], '<td'));
        // The covered column is skipped, so each cell keeps its column's width.
        $this->assertStringContainsString('width: 30%;text-align: right;">ردم', $rows[1]);
        $this->assertStringContainsString('width: 20%;text-align: right;">20', $rows[1]);
    }

    public function test_mpdf_renders_rowspans_grids_and_kept_tables(): void
    {
        $rows = [['القسم', 'البند', 'القيمة']];

        foreach (range(1, 6) as $section) {
            $rows[] = [['text' => 'قسم '.$section, 'rowspan' => 8, 'valign' => 'middle'], 'بند 1', '10.00'];

            foreach (range(2, 8) as $line) {
                $rows[] = ['بند '.$line, '10.00'];
            }
        }

        $pdf = self::table($rows, ['header' => true, 'borders' => 'grid', 'padding' => 3])
            ->table([['المجموع', '480.00'], ['الضريبة', '72.00'], ['الصافي', '552.00']], ['keep_together' => true, 'borders' => 'none'])
            ->pdf()->content();

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_word_borders_grid_rows_and_none(): void
    {
        $grid = self::wordRows(self::table([['أ', 'ب']], ['borders' => 'grid', 'border_width' => 1.5, 'border_color' => '#123456']))[0];
        $rows = self::wordRows(self::table([['أ', 'ب']], ['borders' => true]))[0];
        $none = self::wordRows(self::table([['أ', 'ب']], ['borders' => 'none']))[0];

        foreach (['top', 'left', 'right', 'bottom'] as $side) {
            $this->assertSame(2, substr_count($grid, '<w:'.$side.' w:val="single" w:sz="12" w:color="123456"/>'));
        }

        $this->assertSame(2, substr_count($rows, '<w:tcBorders><w:bottom w:val="single" w:sz="6" w:color="E5E7EB"/></w:tcBorders>'));
        $this->assertStringNotContainsString('<w:tcBorders>', $none);
        $this->assertSame($none, self::wordRows(self::table([['أ', 'ب']], ['borders' => false]))[0]);
    }

    public function test_word_box_border_replaces_the_grid(): void
    {
        $row = self::wordRows(self::table([[['text' => 'أ', 'border' => '#FF0000'], 'ب']], ['borders' => 'grid']))[0];

        preg_match_all('/<w:tcBorders>.*?<\/w:tcBorders>/s', $row, $borders);
        $this->assertSame(4, substr_count($borders[0][0], 'w:color="FF0000"'));
        $this->assertStringNotContainsString('E5E7EB', $borders[0][0]);
    }

    public function test_word_padding_and_vertical_alignment(): void
    {
        $document = self::table([[['text' => 'أ', 'padding' => 4, 'valign' => 'bottom'], 'ب']], ['padding' => 2, 'valign' => 'middle']);
        $row = self::wordRows($document)[0];
        $plain = self::wordRows(self::table([['أ']]))[0];

        $this->assertStringContainsString('<w:tcMar><w:top w:w="227" w:type="dxa"/>', $row);
        $this->assertStringContainsString('<w:vAlign w:val="bottom"/>', $row);
        $this->assertStringContainsString('<w:vAlign w:val="center"/>', $row);
        $this->assertSame(1, substr_count($row, '<w:tcMar>'));
        $this->assertStringContainsString('<w:vAlign w:val="top"/>', $plain);
        $this->assertStringNotContainsString('<w:tcMar>', $plain);
    }

    public function test_word_table_padding_sets_the_cell_margins(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'easy-docx-test');
        file_put_contents($file, self::table([['أ']], ['padding' => 2])->word()->content());
        $zip = new ZipArchive;
        $zip->open($file);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($file);

        $this->assertStringContainsString('<w:tblCellMar><w:top w:w="113" w:type="dxa"/><w:left w:w="113" w:type="dxa"/>', $xml);
    }

    public function test_word_keeps_a_table_together(): void
    {
        $rows = self::wordRows(self::table([['أ'], ['ب'], ['ج']], ['keep_together' => true]));

        $this->assertStringContainsString('<w:keepNext w:val="1"/>', $rows[0]);
        $this->assertStringContainsString('<w:keepNext w:val="1"/>', $rows[1]);
        $this->assertStringNotContainsString('<w:keepNext', $rows[2]);
        $this->assertStringContainsString('<w:cantSplit w:val="1"/>', $rows[2]);
    }

    public function test_word_rowspan_merges_cells(): void
    {
        $rows = self::wordRows(self::table([
            [['text' => 'أعمال مدنية', 'rowspan' => 3, 'background' => '#F1F5F9'], 'حفر', '10'],
            ['ردم', '20'],
            ['دك', '30'],
            ['كهرباء', ['text' => 'مقطوعية', 'colspan' => 2]],
        ], ['columns' => [50, 30, 20]]));

        $this->assertStringContainsString('<w:vMerge w:val="restart"/>', $rows[0]);
        foreach ([1, 2] as $i) {
            $this->assertSame(3, substr_count($rows[$i], '<w:tc>'));
            // The continuation is the first cell, with the width and fill of the merged one.
            $this->assertMatchesRegularExpression('#^<w:tr><w:trPr>.*?</w:trPr><w:tc><w:tcPr><w:tcW w:w="5102" w:type="dxa"/>.*?w:fill="F1F5F9".*?<w:vMerge w:val="continue"/>.*?</w:tcPr><w:p/></w:tc>#s', $rows[$i]);
        }
        $this->assertStringNotContainsString('<w:vMerge', $rows[3]);
        $this->assertStringContainsString('<w:gridSpan w:val="2"/>', $rows[3]);
    }

    public function test_a_document_does_not_forward_row(): void
    {
        $this->expectException(BadMethodCallException::class);

        EasyPdfWord::create()->make()->row(['a']);
    }
}
