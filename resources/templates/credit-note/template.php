<?php

use BiztechEG\EasyPdfWord\Support\Currency;
use BiztechEG\EasyPdfWord\Zatca\ZatcaQr;

/*
| Credit or debit note (إشعار دائن / إشعار مدين) against an issued invoice:
| the original invoice, the reason, the items and amounts being credited
| or charged, VAT, amount in words and an optional QR.
|
| type: "credit" lowers what the buyer owes, "debit" adds to it.
| qr:   "zatca" builds the Saudi phase 1 QR from the seller, date and totals;
|       any other string is encoded as is; null hides the QR.
*/

return [
    'title' => 'Credit note',
    'description' => 'Credit or debit note against an invoice with the reason, items, VAT and optional ZATCA or e-invoice QR.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',

    'fields' => [
        'type' => ['required', 'in:credit,debit'],
        'note.number' => ['required'],
        'note.date' => ['required', 'date'],
        'note.currency' => ['required', 'string', 'size:3'],
        'note.tax_rate' => ['nullable', 'numeric', 'min:0'],
        'invoice.number' => ['required'],
        'invoice.date' => ['nullable', 'date'],
        'reason' => ['required', 'string'],
        'seller' => ['nullable', 'array'],
        'buyer.name' => ['required', 'string'],
        'buyer.address' => ['nullable', 'string'],
        'buyer.tax_number' => ['nullable', 'string'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.description' => ['required', 'string'],
        'items.*.quantity' => ['required', 'numeric', 'min:0'],
        'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        'qr' => ['nullable', 'string'],
        'notes' => ['nullable', 'string'],
    ],

    'defaults' => [
        'type' => 'credit',
        'note' => ['currency' => 'EGP', 'tax_rate' => 14],
        'qr' => null,
    ],

    'prepare' => function (array $data, array $theme = []): array {
        // The seller defaults to the company in the theme (config or ->theme()).
        $data['seller'] = array_merge((array) ($theme['company'] ?? []), array_filter((array) ($data['seller'] ?? []), fn ($v) => $v !== null && $v !== ''));

        $rate = (float) ($data['note']['tax_rate'] ?? 0);
        $decimals = Currency::decimals($data['note']['currency'] ?? null);
        $subtotal = $discount = 0.0;

        // Each line is rounded, so the lines add up to the subtotal.
        // Numbered 1, 2, 3 ... whatever the keys (a filtered collection keeps its keys).
        $data['items'] = array_values($data['items']);

        foreach ($data['items'] as $i => $item) {
            $gross = round((float) $item['quantity'] * (float) $item['unit_price'], $decimals);
            $itemDiscount = round((float) ($item['discount'] ?? 0), $decimals);
            $data['items'][$i]['total'] = round($gross - $itemDiscount, $decimals);
            $subtotal += $gross;
            $discount += $itemDiscount;
        }

        $taxable = $subtotal - $discount;
        $tax = round($taxable * $rate / 100, $decimals);

        $data['totals'] = [
            'subtotal' => round($subtotal, $decimals),
            'discount' => round($discount, $decimals),
            'taxable' => round($taxable, $decimals),
            'tax' => $tax,
            'total' => round($taxable + $tax, $decimals),
        ];

        if (($data['qr'] ?? null) === 'zatca') {
            $data['qr'] = ZatcaQr::make(
                $data['seller']['name'] ?? '',
                $data['seller']['tax_number'] ?? '',
                $data['note']['date'],
                $data['totals']['total'],
                $data['totals']['tax'],
            )->toBase64();
        }

        return $data;
    },

    'sample' => [
        'type' => 'credit',
        'note' => [
            'number' => 'CN-2026-0057',
            'date' => '2026-10-08',
            'currency' => 'EGP',
            'tax_rate' => 14,
        ],
        'invoice' => [
            'number' => 'INV-2026-1024',
            'date' => '2026-09-20',
        ],
        'reason' => 'إلغاء ثلاثة أشهر من اشتراك الاستضافة ويوم تدريب لم يُنفذ',
        'seller' => [
            'name' => 'شركة بيزتك للحلول البرمجية',
            'address' => '15 شارع التحرير، الدقي، الجيزة',
            'tax_number' => '123-456-789',
        ],
        'buyer' => [
            'name' => 'مؤسسة النور للتجارة',
            'address' => 'مدينة نصر، القاهرة',
            'tax_number' => '987-654-321',
        ],
        'items' => [
            ['description' => 'استضافة سحابية - اشتراك شهري', 'quantity' => 3, 'unit_price' => 450],
            ['description' => 'تدريب فريق العمل', 'quantity' => 1, 'unit_price' => 1500],
        ],
        'qr' => 'https://example.com/credit-notes/CN-2026-0057',
        'notes' => 'سيتم خصم قيمة الإشعار من الدفعة القادمة.',
    ],
];
