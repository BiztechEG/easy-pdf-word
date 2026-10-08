<?php

/*
| Delivery note (إذن تسليم): what was handed to the customer, without prices.
| Items show the delivered quantity, and the ordered and remaining
| quantities when "ordered" is given. Transport details, an acknowledgement
| line and signature boxes close the note.
|
| signatures: a list of "storekeeper", "driver", "receiver" or any other
| label, which is printed as it is.
*/

return [
    'title' => 'Delivery note',
    'description' => 'Delivery note without prices: delivered, ordered and remaining quantities, transport details and signatures.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',

    'fields' => [
        'delivery.number' => ['required'],
        'delivery.date' => ['required', 'date'],
        'delivery.order_number' => ['nullable', 'string'],
        'delivery.invoice_number' => ['nullable', 'string'],
        'customer.name' => ['required', 'string'],
        'customer.phone' => ['nullable', 'string'],
        'customer.address' => ['nullable', 'string'],
        'ship_to.address' => ['nullable', 'string'],
        'ship_to.contact' => ['nullable', 'string'],
        'ship_to.phone' => ['nullable', 'string'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.code' => ['nullable', 'string'],
        'items.*.description' => ['required', 'string'],
        'items.*.unit' => ['nullable', 'string'],
        'items.*.quantity' => ['required', 'numeric', 'min:0'],
        'items.*.ordered' => ['nullable', 'numeric', 'min:0'],
        'items.*.notes' => ['nullable', 'string'],
        'packages' => ['nullable', 'integer', 'min:0'],
        'transport.driver' => ['nullable', 'string'],
        'transport.phone' => ['nullable', 'string'],
        'transport.vehicle' => ['nullable', 'string'],
        'notes' => ['nullable', 'string'],
        'signatures' => ['nullable', 'array'],
        'signatures.*' => ['string'],
    ],

    'defaults' => [
        'signatures' => null,
    ],

    'prepare' => function (array $data): array {
        $quantity = 0.0;

        // Numbered 1, 2, 3 ... whatever the keys (a filtered collection keeps its keys).
        $data['items'] = array_values($data['items']);

        foreach ($data['items'] as $i => $item) {
            $quantity += (float) $item['quantity'];

            if (isset($item['ordered']) && $item['ordered'] !== '') {
                $data['items'][$i]['remaining'] = max(0.0, (float) $item['ordered'] - (float) $item['quantity']);
            }
        }

        $data['totals'] = [
            'items' => count($data['items']),
            'quantity' => round($quantity, 3),
        ];

        $data['signatures'] ??= ['storekeeper', 'driver', 'receiver'];

        return $data;
    },

    'sample' => [
        'delivery' => [
            'number' => 'DN-2026-0731',
            'date' => '2026-10-08',
            'order_number' => 'SO-2026-0219',
            'invoice_number' => 'INV-2026-1024',
        ],
        'customer' => [
            'name' => 'مؤسسة النور للتجارة',
            'phone' => '+20 122 555 0100',
            'address' => 'مدينة نصر، القاهرة',
        ],
        'ship_to' => [
            'address' => 'فرع مدينة نصر، 22 شارع عباس العقاد، القاهرة',
            'contact' => 'أحمد عبد الرحمن',
            'phone' => '+20 122 555 0101',
        ],
        'items' => [
            ['code' => 'LAP-14-I5', 'description' => 'لابتوب 14 بوصة Core i5', 'unit' => 'جهاز', 'ordered' => 10, 'quantity' => 10],
            ['code' => 'MON-24', 'description' => 'شاشة 24 بوصة', 'unit' => 'جهاز', 'ordered' => 10, 'quantity' => 6, 'notes' => 'الباقي خلال أسبوع'],
            ['code' => 'KB-AR-EN', 'description' => 'لوحة مفاتيح عربي/إنجليزي', 'unit' => 'قطعة', 'ordered' => 10, 'quantity' => 10],
        ],
        'packages' => 14,
        'transport' => [
            'driver' => 'سامح فؤاد',
            'phone' => '+20 111 333 4444',
            'vehicle' => 'ن ق ط 4821',
        ],
        'notes' => 'تم فحص الأجهزة وتشغيلها أمام المستلم.',
    ],
];
