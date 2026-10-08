<?php

/*
| Certificate (شهادة) for a course, an event or a contribution, on a
| landscape page with a frame: who receives it, what for, the dates, hours
| and grade, up to three signatures and a QR code to verify it.
|
| type:   "completion", "attendance", "participation" or "appreciation".
| gender: "male" or "female", for the Arabic wording (لإتمامه / لإتمامها).
| issuer: who gives it; the company name in the theme by default.
| verify_url: a link to check the certificate, shown as a QR code.
*/

return [
    'title' => 'Certificate',
    'description' => 'Landscape certificate of completion, attendance, participation or appreciation with signatures and a verification QR.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',
    'orientation' => 'landscape',
    'margins' => [10, 10, 10, 10],

    'fields' => [
        'type' => ['required', 'in:completion,attendance,participation,appreciation'],
        'gender' => ['required', 'in:male,female'],
        'number' => ['nullable', 'string'],
        'date' => ['required', 'date'],
        'recipient' => ['required', 'string'],
        'course' => ['required', 'string'],
        'details' => ['nullable', 'string'],
        'from' => ['nullable', 'date'],
        'to' => ['nullable', 'date', 'after_or_equal:from'],
        'hours' => ['nullable', 'numeric', 'min:0'],
        'grade' => ['nullable', 'string'],
        'issuer' => ['nullable', 'string'],
        'signatures' => ['nullable', 'array', 'max:3'],
        'signatures.*.name' => ['required', 'string'],
        'signatures.*.title' => ['nullable', 'string'],
        'verify_url' => ['nullable', 'string'],
    ],

    'defaults' => [
        'type' => 'completion',
        'gender' => 'male',
        'signatures' => [],
    ],

    'prepare' => function (array $data, array $theme = []): array {
        $data['issuer'] = ($data['issuer'] ?? null) ?: ($theme['company']['name'] ?? '');

        // Arabic counts take a different word form: ساعة واحدة، ساعتين، 5 ساعات، 40 ساعة.
        if (isset($data['hours']) && $data['hours'] !== '') {
            $hours = (float) $data['hours'];
            $data['hours_form'] = match (true) {
                $hours == 1 => 'one',
                $hours == 2 => 'two',
                floor($hours) == $hours && $hours >= 3 && $hours <= 10 => 'few',
                default => 'many',
            };
        }

        return $data;
    },

    'sample' => [
        'type' => 'completion',
        'gender' => 'female',
        'number' => 'CRT-2026-00731',
        'date' => '2026-10-08',
        'recipient' => 'سارة محمود عبد الله',
        'course' => 'تطوير تطبيقات الويب باستخدام Laravel',
        'from' => '2026-09-06',
        'to' => '2026-10-01',
        'hours' => 40,
        'grade' => 'امتياز',
        'issuer' => 'أكاديمية بيزتك للتدريب',
        'signatures' => [
            ['name' => 'م. أحمد عبد الرحمن', 'title' => 'المدرب'],
            ['name' => 'عمرو محمد', 'title' => 'مدير الأكاديمية'],
        ],
        'verify_url' => 'https://example.com/certificates/CRT-2026-00731',
    ],
];
