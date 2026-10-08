<?php

/*
| Contract (عقد): a title, the date and place, two or more parties, an
| optional preamble, numbered clauses (البند الأول، البند الثاني ...),
| the number of copies, signature boxes for every party and witnesses.
| Works for service, supply, rental or employment contracts alike.
|
| parties.*.alias: how the contract refers to the party, e.g. "مقدم الخدمة".
| clauses.*.text:  each line starts a new paragraph.
| closing:         replaces the sentence about the copies.
*/

return [
    'title' => 'Contract',
    'description' => 'Contract between two or more parties with a preamble, numbered clauses, copies, signatures and witnesses.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',
    'margins' => [18, 20, 28, 20],

    'fields' => [
        'contract.title' => ['required', 'string'],
        'contract.number' => ['nullable', 'string'],
        'contract.date' => ['required', 'date'],
        'contract.place' => ['nullable', 'string'],
        'parties' => ['required', 'array', 'min:2'],
        'parties.*.name' => ['required', 'string'],
        'parties.*.alias' => ['nullable', 'string'],
        'parties.*.id_label' => ['nullable', 'string'],
        'parties.*.id' => ['nullable', 'string'],
        'parties.*.address' => ['nullable', 'string'],
        'parties.*.represented_by' => ['nullable', 'string'],
        'parties.*.capacity' => ['nullable', 'string'],
        'preamble' => ['nullable', 'string'],
        'clauses' => ['required', 'array', 'min:1'],
        'clauses.*.title' => ['nullable', 'string'],
        'clauses.*.text' => ['required', 'string'],
        'copies' => ['nullable', 'integer', 'min:1'],
        'closing' => ['nullable', 'string'],
        'witnesses' => ['nullable', 'array'],
        'witnesses.*' => ['string'],
    ],

    'defaults' => [
        'copies' => 2,
        'witnesses' => [],
    ],

    'prepare' => function (array $data): array {
        $lines = fn (?string $text) => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $text))));

        $data['parties'] = array_values($data['parties']);
        $data['preamble_paragraphs'] = $lines($data['preamble'] ?? null);

        $data['clauses'] = array_map(fn (array $clause) => $clause + ['paragraphs' => $lines($clause['text'])], array_values($data['clauses']));

        return $data;
    },

    'sample' => [
        'contract' => [
            'title' => 'عقد تقديم خدمات برمجية',
            'number' => 'C-2026-031',
            'date' => '2026-10-08',
            'place' => 'القاهرة',
        ],
        'parties' => [
            [
                'name' => 'شركة بيزتك للحلول البرمجية',
                'alias' => 'مقدم الخدمة',
                'id_label' => 'سجل تجاري رقم',
                'id' => '123456',
                'address' => '15 شارع التحرير، الدقي، الجيزة',
                'represented_by' => 'عمرو محمد',
                'capacity' => 'المدير التنفيذي',
            ],
            [
                'name' => 'مؤسسة النور للتجارة',
                'alias' => 'العميل',
                'id_label' => 'سجل تجاري رقم',
                'id' => '654321',
                'address' => 'مدينة نصر، القاهرة',
                'represented_by' => 'أحمد عبد الرحمن',
                'capacity' => 'المدير العام',
            ],
        ],
        'preamble' => 'يعمل الطرف الأول في مجال تطوير البرمجيات وأنظمة إدارة الأعمال، ويرغب الطرف الثاني في تطوير نظام لإدارة المخزون والمبيعات يناسب طبيعة نشاطه، وقد اطلع على عرض السعر المقدم من الطرف الأول رقم QT-2026-0088 وقبله.',
        'clauses' => [
            ['title' => 'التمهيد', 'text' => 'يعتبر التمهيد السابق وعرض السعر المشار إليه جزءاً لا يتجزأ من هذا العقد ويُقرأ ويُفسر معه.'],
            ['title' => 'موضوع العقد', 'text' => "يلتزم الطرف الأول بتحليل وتصميم وتطوير نظام لإدارة المخزون والمبيعات للطرف الثاني، يشمل:\n1. إدارة الأصناف والمخازن وحركات الوارد والصادر.\n2. فواتير المبيعات والمشتريات وإشعارات الخصم والإضافة.\n3. التقارير الدورية باللغتين العربية والإنجليزية."],
            ['title' => 'مدة التنفيذ', 'text' => 'مدة تنفيذ العقد خمسة وأربعون يوم عمل تبدأ من تاريخ استلام الدفعة الأولى، ولا تُحتسب منها مدد التأخير الراجعة إلى الطرف الثاني.'],
            ['title' => 'قيمة العقد وطريقة السداد', 'text' => "قيمة العقد الإجمالية 71,250 جنيهاً مصرياً شاملة ضريبة القيمة المضافة، تُسدد على النحو التالي:\nالدفعة الأولى 40% عند توقيع العقد.\nالدفعة الثانية 40% عند التسليم الابتدائي.\nالدفعة الأخيرة 20% بعد شهر من التشغيل الفعلي."],
            ['title' => 'التزامات الطرف الثاني', 'text' => 'يلتزم الطرف الثاني بتوفير البيانات والمعلومات اللازمة للتنفيذ وتحديد مسؤول للتواصل، وبسداد الدفعات في مواعيدها.'],
            ['title' => 'السرية', 'text' => 'يلتزم كل طرف بالمحافظة على سرية المعلومات التي يطلع عليها بسبب هذا العقد، ويستمر هذا الالتزام بعد انتهاء العقد.'],
            ['title' => 'الضمان والدعم الفني', 'text' => 'يضمن الطرف الأول خلو النظام من الأخطاء البرمجية لمدة اثني عشر شهراً من تاريخ التسليم النهائي، ويقدم خلالها الدعم الفني دون مقابل.'],
            ['title' => 'فض النزاعات', 'text' => 'يخضع هذا العقد لأحكام القانون المصري، وتختص محاكم القاهرة بنظر أي نزاع ينشأ عنه إذا تعذر حله ودياً.'],
        ],
        'witnesses' => ['محمود علي', 'منى سامي'],
    ],
];
