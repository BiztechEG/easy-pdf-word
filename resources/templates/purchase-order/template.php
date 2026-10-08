<?php

use BiztechEG\EasyPdfWord\Support\Currency;

/*
| Purchase order (أمر شراء) sent to a supplier: items with codes, units and
| prices, discount, optional VAT, delivery date and place, payment terms and
| signature boxes.
|
| signatures: a list of "prepared_by", "approved_by", "supplier" or any
| other label, which is printed as it is.
*/

return [
    'title' => 'Purchase order',
    'description' => 'Purchase order to a supplier with items, optional VAT, delivery details, payment terms and approvals.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',

    'fields' => [
        'order.number' => ['required'],
        'order.date' => ['required', 'date'],
        'order.delivery_date' => ['nullable', 'date'],
        'order.currency' => ['required', 'string', 'size:3'],
        'order.tax_rate' => ['nullable', 'numeric', 'min:0'],
        'order.reference' => ['nullable', 'string'],
        'order.payment_terms' => ['nullable', 'string'],
        'supplier.name' => ['required', 'string'],
        'supplier.contact' => ['nullable', 'string'],
        'supplier.phone' => ['nullable', 'string'],
        'supplier.address' => ['nullable', 'string'],
        'supplier.tax_number' => ['nullable', 'string'],
        'delivery.address' => ['nullable', 'string'],
        'delivery.contact' => ['nullable', 'string'],
        'delivery.phone' => ['nullable', 'string'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.code' => ['nullable', 'string'],
        'items.*.description' => ['required', 'string'],
        'items.*.unit' => ['nullable', 'string'],
        'items.*.quantity' => ['required', 'numeric', 'min:0'],
        'items.*.unit_price' => ['required', 'numeric'],
        'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        'terms' => ['nullable', 'array'],
        'notes' => ['nullable', 'string'],
        'signatures' => ['nullable', 'array'],
        'signatures.*' => ['string'],
    ],

    'defaults' => [
        'order' => ['currency' => 'EGP', 'tax_rate' => 0],
        'terms' => [],
        'signatures' => null,
    ],

    'prepare' => function (array $data): array {
        $rate = (float) ($data['order']['tax_rate'] ?? 0);
        $decimals = Currency::decimals($data['order']['currency'] ?? null);
        $subtotal = $discount = 0.0;

        // Each line is rounded, so the lines add up to the subtotal.
        foreach ($data['items'] as $i => $item) {
            $gross = round((float) $item['quantity'] * (float) $item['unit_price'], $decimals);
            $itemDiscount = round((float) ($item['discount'] ?? 0), $decimals);
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

        $data['signatures'] ??= ['prepared_by', 'approved_by', 'supplier'];

        return $data;
    },

    'sample' => [
        'order' => [
            'number' => 'PO-2026-0412',
            'date' => '2026-10-08',
            'delivery_date' => '2026-10-20',
            'currency' => 'EGP',
            'tax_rate' => 14,
            'reference' => 'QT-5531',
            'payment_terms' => 'الدفع بتحويل بنكي خلال 30 يوماً من الاستلام وتقديم الفاتورة الضريبية.',
        ],
        'supplier' => [
            'name' => 'شركة الأمل لتوريد مستلزمات المكاتب',
            'contact' => 'الأستاذة منى سامي',
            'phone' => '+20 2 2345 6789',
            'address' => 'المنطقة الصناعية الثالثة، العاشر من رمضان',
            'tax_number' => '456-789-123',
        ],
        'delivery' => [
            'address' => 'المخزن الرئيسي، 15 شارع التحرير، الدقي، الجيزة',
            'contact' => 'محمود علي (أمين المخزن)',
            'phone' => '+20 100 111 2222',
        ],
        'items' => [
            ['code' => 'PPR-A4-80', 'description' => 'ورق طباعة A4 وزن 80 جم', 'unit' => 'كرتونة', 'quantity' => 40, 'unit_price' => 950],
            ['code' => 'TNR-26A', 'description' => 'حبر طابعة HP 26A أصلي', 'unit' => 'قطعة', 'quantity' => 10, 'unit_price' => 3200, 'discount' => 1500],
            ['code' => 'CHR-ERG-01', 'description' => 'كرسي مكتب طبي بمسند ظهر', 'unit' => 'قطعة', 'quantity' => 6, 'unit_price' => 4750],
        ],
        'terms' => [
            'يتم التوريد دفعة واحدة إلى عنوان التسليم الموضح.',
            'يحق للشركة رفض أي صنف مخالف للمواصفات أو تالف عند الاستلام.',
            'يجب ذكر رقم أمر الشراء على الفاتورة وإذن التسليم.',
        ],
    ],
];
