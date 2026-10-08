<?php

use BiztechEG\EasyPdfWord\Support\Currency;
use BiztechEG\EasyPdfWord\Zatca\ZatcaQr;

/*
| Tax invoice for Egypt (VAT 14%) and Saudi Arabia (VAT 15%, ZATCA QR).
|
| qr: "zatca" builds the Saudi phase 1 QR from the seller, date and totals;
|     any other string (an e-invoice link, a UUID) is encoded as is;
|     null hides the QR.
*/

return [
    'title' => 'Tax invoice',
    'description' => 'Tax invoice with items, VAT, amount in words and optional ZATCA or e-invoice QR.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',

    'fields' => [
        'invoice.number' => ['required'],
        'invoice.date' => ['required', 'date'],
        'invoice.due_date' => ['nullable', 'date'],
        'invoice.currency' => ['required', 'string', 'size:3'],
        'invoice.tax_rate' => ['nullable', 'numeric', 'min:0'],
        'invoice.notes' => ['nullable', 'string'],
        'invoice.eta_uuid' => ['nullable', 'string'],
        'seller' => ['nullable', 'array'],
        'buyer.name' => ['required', 'string'],
        'buyer.address' => ['nullable', 'string'],
        'buyer.tax_number' => ['nullable', 'string'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.description' => ['required', 'string'],
        'items.*.quantity' => ['required', 'numeric'],
        'items.*.unit_price' => ['required', 'numeric'],
        'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        'qr' => ['nullable', 'string'],
    ],

    'defaults' => [
        'invoice' => ['currency' => 'EGP', 'tax_rate' => 14],
        'qr' => null,
    ],

    'prepare' => function (array $data, array $theme = []): array {
        // The seller defaults to the company in the theme (config or ->theme()).
        $data['seller'] = array_merge((array) ($theme['company'] ?? []), array_filter((array) ($data['seller'] ?? []), fn ($v) => $v !== null && $v !== ''));

        $rate = (float) ($data['invoice']['tax_rate'] ?? 0);
        $decimals = Currency::decimals($data['invoice']['currency'] ?? null);
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
                $data['invoice']['date'],
                $data['totals']['total'],
                $data['totals']['tax'],
            )->toBase64();
        }

        return $data;
    },

    'sample' => [
        'invoice' => [
            'number' => 'INV-2026-1024',
            'date' => '2026-10-08',
            'due_date' => '2026-10-22',
            'currency' => 'EGP',
            'tax_rate' => 14,
            'notes' => 'يرجى التحويل على الحساب البنكي خلال 14 يوماً من تاريخ الفاتورة.',
        ],
        'seller' => [
            'name' => 'شركة بيزتك للحلول البرمجية',
            'address' => '15 شارع التحرير، الدقي، الجيزة',
            'tax_number' => '123-456-789',
            'phone' => '+20 100 000 0000',
        ],
        'buyer' => [
            'name' => 'مؤسسة النور للتجارة',
            'address' => 'مدينة نصر، القاهرة',
            'tax_number' => '987-654-321',
        ],
        'items' => [
            ['description' => 'تطوير نظام إدارة المخزون (Laravel)', 'quantity' => 1, 'unit_price' => 25000],
            ['description' => 'استضافة سحابية - اشتراك سنوي', 'quantity' => 12, 'unit_price' => 450, 'discount' => 400],
            ['description' => 'تدريب فريق العمل', 'quantity' => 3, 'unit_price' => 1500],
        ],
        'qr' => 'https://example.com/invoices/INV-2026-1024',
    ],
];
