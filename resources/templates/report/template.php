<?php

use BiztechEG\EasyPdfWord\Support\DocContext;

/*
| Table report: any list of rows (arrays, Eloquent models, collections) with
| chosen columns, optional totals row and summary boxes. The table header
| repeats on every page.
|
| columns: ['name' => 'الاسم', ...]
|      or  [['key' => 'amount', 'label' => 'المبلغ', 'format' => 'number', 'decimals' => 2], ...]
| sum:     keys to total in the last row, e.g. ['amount']
*/

return [
    'title' => 'Table report',
    'description' => 'Data table report with repeated header, totals row, summary cards and page numbers.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',
    'margins' => [15, 12, 16, 12],

    'fields' => [
        'title' => ['required', 'string'],
        'subtitle' => ['nullable', 'string'],
        'columns' => ['required', 'array', 'min:1'],
        'rows' => ['present'],
        'sum' => ['nullable', 'array'],
        'summary' => ['nullable', 'array'],
        'generated_at' => ['nullable', 'date'],
    ],

    'defaults' => [
        'sum' => [],
        'summary' => [],
    ],

    'prepare' => function (array $data): array {
        $columns = [];

        foreach ($data['columns'] as $key => $column) {
            $column = is_array($column) ? $column : ['key' => $key, 'label' => $column];
            $column['key'] ??= $key;
            $column['label'] ??= (string) $column['key'];
            $column['format'] ??= null;
            $column['decimals'] ??= 2;
            $column['align'] ??= in_array($column['format'], ['number', 'money'], true) ? 'end' : 'start';
            $columns[] = $column;
        }

        $rows = [];

        foreach ($data['rows'] ?? [] as $row) {
            $rows[] = match (true) {
                is_array($row) => $row,
                $row instanceof \Illuminate\Contracts\Support\Arrayable => $row->toArray(),
                default => (array) $row,
            };
        }

        $totals = [];

        foreach ((array) $data['sum'] as $key) {
            // Read like the cells are: "1,240" is 1240, not 1.
            $totals[$key] = array_sum(array_map(fn ($row) => is_scalar($value = data_get($row, $key)) ? DocContext::toFloat($value) : 0.0, $rows));
        }

        $data['columns'] = $columns;
        $data['rows'] = $rows;
        $data['totals'] = $totals;
        $data['generated_at'] ??= now();

        return $data;
    },

    'sample' => [
        'title' => 'تقرير المبيعات الشهري',
        'subtitle' => 'الفترة من 1 سبتمبر حتى 30 سبتمبر 2026',
        'columns' => [
            ['key' => 'branch', 'label' => 'الفرع'],
            ['key' => 'manager', 'label' => 'المدير'],
            ['key' => 'orders', 'label' => 'عدد الطلبات', 'format' => 'number', 'decimals' => 0],
            ['key' => 'revenue', 'label' => 'الإيرادات (ج.م)', 'format' => 'number'],
            ['key' => 'growth', 'label' => 'النمو %', 'format' => 'number', 'decimals' => 1],
        ],
        'rows' => [
            ['branch' => 'القاهرة - مدينة نصر', 'manager' => 'سارة إبراهيم', 'orders' => 1240, 'revenue' => 486500.75, 'growth' => 12.4],
            ['branch' => 'الجيزة - الدقي', 'manager' => 'محمد علي', 'orders' => 980, 'revenue' => 371200, 'growth' => 8.1],
            ['branch' => 'الإسكندرية - سموحة', 'manager' => 'Omar Khaled', 'orders' => 765, 'revenue' => 290340.5, 'growth' => -2.3],
            ['branch' => 'المنصورة', 'manager' => 'هبة سمير', 'orders' => 410, 'revenue' => 150875, 'growth' => 4.7],
        ],
        'sum' => ['orders', 'revenue'],
        'summary' => [
            'إجمالي الإيرادات' => '1,298,916.25 ج.م',
            'أعلى فرع' => 'مدينة نصر',
            'عدد الفروع' => '4',
        ],
        'generated_at' => '2026-10-08 10:30',
    ],
];
