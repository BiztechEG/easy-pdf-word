<?php

/*
| Official letter (خطاب رسمي) on a letterhead: reference number, Gregorian
| and Hijri dates, recipient, subject, body, signature and stamp.
|
| body: a string (blank lines start new paragraphs) or an array of paragraphs.
*/

return [
    'title' => 'Official letter',
    'description' => 'Official letter with letterhead, reference number, Hijri date, subject, signature and stamp.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',
    'margins' => [18, 20, 20, 20],

    'fields' => [
        'reference' => ['nullable', 'string'],
        'date' => ['required', 'date'],
        'show_hijri' => ['nullable', 'boolean'],
        'recipient.name' => ['required', 'string'],
        'recipient.title' => ['nullable', 'string'],
        'recipient.organization' => ['nullable', 'string'],
        'subject' => ['required', 'string'],
        'greeting' => ['nullable', 'string'],
        'body' => ['required'],
        'closing' => ['nullable', 'string'],
        'sender.name' => ['required', 'string'],
        'sender.title' => ['nullable', 'string'],
        'signature' => ['nullable', 'string'],
        'stamp' => ['nullable', 'string'],
        'cc' => ['nullable', 'array'],
    ],

    'defaults' => [
        'show_hijri' => true,
        'cc' => [],
    ],

    'prepare' => function (array $data): array {
        $body = $data['body'];

        $data['paragraphs'] = is_array($body)
            ? array_values(array_filter(array_map('trim', $body)))
            : array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', (string) $body))));

        return $data;
    },

    'sample' => [
        'reference' => 'ص/2026/417',
        'date' => '2026-10-08',
        'recipient' => [
            'name' => 'المهندس أحمد عبد الرحمن',
            'title' => 'مدير إدارة تقنية المعلومات',
            'organization' => 'مؤسسة النور للتجارة',
        ],
        'subject' => 'عرض تنفيذ نظام إدارة المستندات',
        'body' => "إشارة إلى اجتماعنا المنعقد بتاريخ 1 أكتوبر 2026، يسعدنا أن نقدم لكم عرضنا لتنفيذ نظام إدارة المستندات الإلكترونية، والذي يشمل إصدار الفواتير والخطابات والتقارير باللغتين العربية والإنجليزية.\n\nتبلغ مدة التنفيذ 45 يوم عمل من تاريخ التعاقد، ويتضمن العرض تدريب فريقكم والدعم الفني لمدة 12 شهراً.\n\nنأمل أن ينال عرضنا رضاكم، ونحن على استعداد للرد على أي استفسارات.",
        'sender' => [
            'name' => 'عمرو محمد',
            'title' => 'المدير التنفيذي',
        ],
        'cc' => ['الإدارة المالية', 'الملف العام'],
    ],
];
