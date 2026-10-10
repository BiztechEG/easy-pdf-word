<?php

use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;
use BiztechEG\EasyPdfWord\Support\Currency;

/*
| Price quotation (عرض سعر): customer, items with optional details and units,
| discount, optional VAT, validity date, terms and the sender's signature.
*/

return [
    'title' => 'Price quotation',
    'description' => 'Price quotation with items, optional VAT, validity date, terms and signature.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',

    'fields' => [
        'quote.number' => ['required'],
        'quote.date' => ['required', 'date'],
        'quote.valid_until' => ['nullable', 'date'],
        'quote.currency' => ['required', 'string', 'size:3'],
        'quote.tax_rate' => ['nullable', 'numeric', 'min:0'],
        'customer.name' => ['required', 'string'],
        'customer.contact' => ['nullable', 'string'],
        'customer.phone' => ['nullable', 'string'],
        'customer.address' => ['nullable', 'string'],
        'subject' => ['nullable', 'string'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.description' => ['required', 'string'],
        'items.*.details' => ['nullable', 'string'],
        'items.*.unit' => ['nullable', 'string'],
        'items.*.quantity' => ['required', 'numeric'],
        'items.*.unit_price' => ['required', 'numeric'],
        'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        'terms' => ['nullable', 'array'],
        'notes' => ['nullable', 'string'],
        'sender.name' => ['nullable', 'string'],
        'sender.title' => ['nullable', 'string'],
    ],

    'defaults' => [
        'quote' => ['currency' => 'EGP', 'tax_rate' => 0],
        'terms' => [],
    ],

    'prepare' => function (array $data): array {
        $rate = (float) ($data['quote']['tax_rate'] ?? 0);
        $decimals = Currency::decimals($data['quote']['currency'] ?? null);
        $subtotal = $discount = 0.0;

        // Each line is rounded, so the lines add up to the subtotal.
        // Numbered 1, 2, 3 ... whatever the keys (a filtered collection keeps its keys).
        $data['items'] = array_values($data['items']);

        foreach ($data['items'] as $i => $item) {
            $gross = round((float) $item['quantity'] * (float) $item['unit_price'], $decimals);
            $itemDiscount = round((float) ($item['discount'] ?? 0), $decimals);

            if ($itemDiscount > $gross) {
                throw ValidationFailed::withMessages(["items.{$i}.discount" => 'The discount cannot be more than the line amount (quantity × unit price).']);
            }

            $data['items'][$i]['total'] = round($gross - $itemDiscount, $decimals);
            $subtotal += $gross;
            $discount += $itemDiscount;
        }

        $net = $subtotal - $discount;
        $tax = round($net * $rate / 100, $decimals);

        $data['totals'] = [
            'subtotal' => round($subtotal, $decimals),
            'discount' => round($discount, $decimals),
            'net' => round($net, $decimals),
            'tax' => $tax,
            'total' => round($net + $tax, $decimals),
        ];

        return $data;
    },

    'sample' => [
        'quote' => [
            'number' => 'QT-2026-0088',
            'date' => '2026-10-08',
            'valid_until' => '2026-11-07',
            'currency' => 'EGP',
            'tax_rate' => 14,
        ],
        'customer' => [
            'name' => 'مؤسسة النور للتجارة',
            'contact' => 'المهندس أحمد عبد الرحمن',
            'phone' => '+20 122 555 0100',
            'address' => 'مدينة نصر، القاهرة',
        ],
        'subject' => 'تنفيذ نظام إدارة المستندات الإلكترونية',
        'items' => [
            ['description' => 'تحليل المتطلبات وتصميم النظام', 'details' => 'ورش عمل مع الأقسام وتوثيق كامل', 'unit' => 'مرحلة', 'quantity' => 1, 'unit_price' => 18000],
            ['description' => 'تطوير النظام (Laravel)', 'details' => 'الفواتير والخطابات والتقارير بالعربية والإنجليزية', 'unit' => 'مرحلة', 'quantity' => 1, 'unit_price' => 42000, 'discount' => 2000],
            ['description' => 'تدريب المستخدمين', 'unit' => 'يوم', 'quantity' => 3, 'unit_price' => 1500],
        ],
        'terms' => [
            'يسري هذا العرض لمدة 30 يوماً من تاريخه.',
            'الدفع: 40% عند التعاقد، و40% عند التسليم، و20% بعد شهر من التشغيل.',
            'مدة التنفيذ 45 يوم عمل من تاريخ استلام الدفعة الأولى.',
            'الدعم الفني مجاني لمدة 12 شهراً.',
        ],
        'sender' => ['name' => 'عمرو محمد', 'title' => 'المدير التنفيذي'],
    ],
];
