<?php

/*
| Receipt or payment voucher (سند قبض / سند صرف): the amount in figures and
| words, who paid or was paid, what for, how (cash, cheque, transfer, card)
| and signature boxes.
|
| type: "receipt" (money received) or "payment" (money paid out).
*/

return [
    'title' => 'Receipt voucher',
    'description' => 'Receipt or payment voucher with amount in words, payment method and signatures.',
    'locales' => ['ar', 'en'],
    'paper' => 'A5',
    'orientation' => 'landscape',
    'margins' => [10, 12, 12, 12],

    'fields' => [
        'type' => ['required', 'in:receipt,payment'],
        'number' => ['required'],
        'date' => ['required', 'date'],
        'amount' => ['required', 'numeric', 'min:0'],
        'currency' => ['required', 'string', 'size:3'],
        'party' => ['required', 'string'],
        'for' => ['required', 'string'],
        'method' => ['required', 'in:cash,cheque,transfer,card'],
        'cheque.number' => ['required_if:method,cheque', 'nullable', 'string'],
        'cheque.bank' => ['nullable', 'string'],
        'cheque.date' => ['nullable', 'date'],
        'reference' => ['nullable', 'string'],
        'notes' => ['nullable', 'string'],
        'signatures' => ['nullable', 'array'],
    ],

    'defaults' => [
        'type' => 'receipt',
        'currency' => 'EGP',
        'method' => 'cash',
        'signatures' => null,
    ],

    'prepare' => function (array $data): array {
        $data['amount'] = round((float) $data['amount'], 2);
        $data['signatures'] ??= $data['type'] === 'payment'
            ? ['receiver', 'accountant', 'manager']
            : ['payer', 'cashier', 'accountant'];

        return $data;
    },

    'sample' => [
        'type' => 'receipt',
        'number' => 'RV-2026-0315',
        'date' => '2026-10-08',
        'amount' => 15750.5,
        'currency' => 'EGP',
        'party' => 'مؤسسة النور للتجارة',
        'for' => 'الدفعة الثانية من قيمة عقد تطوير نظام إدارة المخزون',
        'method' => 'cheque',
        'cheque' => ['number' => '00045871', 'bank' => 'البنك الأهلي المصري', 'date' => '2026-10-15'],
        'notes' => 'يعتبر هذا السند لاغياً في حالة عدم صرف الشيك.',
    ],
];
