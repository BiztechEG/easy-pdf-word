<?php

use BiztechEG\EasyPdfWord\Support\Currency;

/*
| Payslip (قسيمة راتب): the employee, the month, earnings and deductions
| side by side, the net salary in figures and words, attendance, how it
| was paid and signature boxes.
|
| period:     the month as "2026-09".
| signatures: a list of "accountant", "hr", "employee" or any other label,
|             which is printed as it is.
*/

return [
    'title' => 'Payslip',
    'description' => 'Monthly payslip with earnings, deductions, net salary in words, attendance and signatures.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',

    'fields' => [
        'period' => ['required', 'date_format:Y-m'],
        'number' => ['nullable', 'string'],
        'currency' => ['required', 'string', 'size:3'],
        'employee.name' => ['required', 'string'],
        'employee.code' => ['nullable', 'string'],
        'employee.job_title' => ['nullable', 'string'],
        'employee.department' => ['nullable', 'string'],
        'employee.national_id' => ['nullable', 'string'],
        'employee.hire_date' => ['nullable', 'date'],
        'employee.bank' => ['nullable', 'string'],
        'employee.bank_account' => ['nullable', 'string'],
        'earnings' => ['required', 'array', 'min:1'],
        'earnings.*.name' => ['required', 'string'],
        'earnings.*.amount' => ['required', 'numeric', 'min:0'],
        'deductions' => ['nullable', 'array'],
        'deductions.*.name' => ['required', 'string'],
        'deductions.*.amount' => ['required', 'numeric', 'min:0'],
        'attendance.working_days' => ['nullable', 'numeric', 'min:0'],
        'attendance.present_days' => ['nullable', 'numeric', 'min:0'],
        'attendance.absent_days' => ['nullable', 'numeric', 'min:0'],
        'attendance.leave_days' => ['nullable', 'numeric', 'min:0'],
        'attendance.overtime_hours' => ['nullable', 'numeric', 'min:0'],
        'payment.method' => ['nullable', 'in:bank,cash,cheque'],
        'payment.date' => ['nullable', 'date'],
        'notes' => ['nullable', 'string'],
        'signatures' => ['nullable', 'array'],
        'signatures.*' => ['string'],
    ],

    'defaults' => [
        'currency' => 'EGP',
        'deductions' => [],
        'signatures' => null,
    ],

    'prepare' => function (array $data): array {
        $decimals = Currency::decimals($data['currency']);
        $sum = function (string $key) use (&$data, $decimals): float {
            $total = 0.0;

            foreach ($data[$key] ?? [] as $i => $line) {
                $data[$key][$i]['amount'] = round((float) $line['amount'], $decimals);
                $total += $data[$key][$i]['amount'];
            }

            return round($total, $decimals);
        };

        $earnings = $sum('earnings');
        $deductions = $sum('deductions');

        $data['totals'] = [
            'earnings' => $earnings,
            'deductions' => $deductions,
            'net' => round($earnings - $deductions, $decimals),
        ];

        $data['signatures'] ??= ['accountant', 'hr', 'employee'];

        return $data;
    },

    'sample' => [
        'period' => '2026-09',
        'number' => 'PS-2026-09-0142',
        'currency' => 'EGP',
        'employee' => [
            'name' => 'سارة محمود عبد الله',
            'code' => 'EMP-0142',
            'job_title' => 'مطورة برمجيات أولى',
            'department' => 'تقنية المعلومات',
            'national_id' => '29501011234567',
            'hire_date' => '2022-03-01',
            'bank' => 'البنك الأهلي المصري',
            'bank_account' => 'EG38 0003 0000 0000 1234 5678 901',
        ],
        'earnings' => [
            ['name' => 'الراتب الأساسي', 'amount' => 18000],
            ['name' => 'بدل سكن', 'amount' => 3000],
            ['name' => 'بدل انتقال', 'amount' => 1200],
            ['name' => 'ساعات إضافية', 'amount' => 1450],
        ],
        'deductions' => [
            ['name' => 'التأمينات الاجتماعية', 'amount' => 1980],
            ['name' => 'ضريبة كسب العمل', 'amount' => 2135.5],
            ['name' => 'قسط سلفة', 'amount' => 1000],
        ],
        'attendance' => [
            'working_days' => 22,
            'present_days' => 21,
            'leave_days' => 1,
            'overtime_hours' => 10,
        ],
        'payment' => ['method' => 'bank', 'date' => '2026-09-28'],
    ],
];
