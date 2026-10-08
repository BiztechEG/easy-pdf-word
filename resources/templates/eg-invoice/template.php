<?php

/*
| Egyptian e-invoice (منظومة الفاتورة الإلكترونية - ETA): the printed copy
| of a document submitted to the Egyptian Tax Authority.
|
| Field names follow the ETA document, so the same data you submit can be
| printed: issuer/receiver with tax registration number, branch and
| activity code, item codes (EGS/GS1), units, and taxes per line by ETA
| type (T1 VAT, T2/T3 table tax, T4 withholding, ...).
|
| Line taxes: [['type' => 'T1', 'rate' => 14], ['type' => 'T4', 'subtype' => 'W010', 'rate' => 1]]
| or a fixed amount: ['type' => 'T3', 'amount' => 50].
| VAT (T1) is charged on the net amount plus the other taxes, as ETA
| computes it; withholding (T4) is deducted.
|
| The QR links to the document on the ETA portal when uuid and long_id are
| given; "qr" overrides it.
*/

return [
    'title' => 'Egyptian e-invoice',
    'description' => 'Printed copy of an Egyptian Tax Authority e-invoice, credit or debit note, with item codes, ETA tax types and portal QR.',
    'locales' => ['ar', 'en'],
    'paper' => 'A4',
    'margins' => [12, 12, 15, 12],

    'fields' => [
        'document.type' => ['required', 'in:I,C,D'],
        'document.internal_id' => ['required'],
        'document.issued_at' => ['required', 'date'],
        'document.uuid' => ['nullable', 'string'],
        'document.long_id' => ['nullable', 'string'],
        'document.submission_uuid' => ['nullable', 'string'],
        'document.purchase_order' => ['nullable', 'string'],
        'document.currency' => ['required', 'string', 'size:3'],
        'issuer.name' => ['required', 'string'],
        'issuer.rin' => ['required', 'string'],
        'issuer.branch_id' => ['nullable'],
        'issuer.activity_code' => ['nullable', 'string'],
        'issuer.address' => ['nullable', 'string'],
        'receiver.type' => ['required', 'in:B,P,F'],
        'receiver.id' => ['nullable', 'string'],
        'receiver.name' => ['required', 'string'],
        'receiver.address' => ['nullable', 'string'],
        'lines' => ['required', 'array', 'min:1'],
        'lines.*.description' => ['required', 'string'],
        'lines.*.item_type' => ['nullable', 'in:EGS,GS1'],
        'lines.*.item_code' => ['nullable', 'string'],
        'lines.*.unit' => ['nullable', 'string'],
        'lines.*.quantity' => ['required', 'numeric'],
        'lines.*.unit_price' => ['required', 'numeric'],
        'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
        'lines.*.taxes' => ['nullable', 'array'],
        'lines.*.taxes.*.type' => ['required', 'regex:/^T([1-9]|1[0-9]|20)$/'],
        'lines.*.taxes.*.rate' => ['nullable', 'numeric'],
        'lines.*.taxes.*.amount' => ['nullable', 'numeric'],
        'extra_discount' => ['nullable', 'numeric', 'min:0'],
        'qr' => ['nullable', 'string'],
        'notes' => ['nullable', 'string'],
    ],

    'defaults' => [
        'document' => ['type' => 'I', 'currency' => 'EGP'],
        'receiver' => ['type' => 'B'],
        'extra_discount' => 0,
    ],

    'prepare' => function (array $data, array $theme = []): array {
        $taxTotals = [];
        $sales = $discounts = $net = $total = 0.0;

        foreach ($data['lines'] as $i => $line) {
            $lineSales = round((float) $line['quantity'] * (float) $line['unit_price'], 5);
            $lineDiscount = (float) ($line['discount'] ?? 0);
            $lineNet = $lineSales - $lineDiscount;
            $taxes = [];

            // Non-VAT taxes first: VAT is charged on the net plus these.
            $others = 0.0;

            foreach ($line['taxes'] ?? [] as $tax) {
                $type = strtoupper($tax['type']);

                if ($type === 'T1') {
                    continue;
                }

                $amount = round(isset($tax['amount']) ? (float) $tax['amount'] : $lineNet * (float) ($tax['rate'] ?? 0) / 100, 5);
                $taxes[] = ['type' => $type, 'subtype' => $tax['subtype'] ?? null, 'rate' => $tax['rate'] ?? null, 'amount' => $amount];

                if ($type !== 'T4') {
                    $others += $amount;
                }
            }

            foreach ($line['taxes'] ?? [] as $tax) {
                if (strtoupper($tax['type']) === 'T1') {
                    $amount = round(isset($tax['amount']) ? (float) $tax['amount'] : ($lineNet + $others) * (float) ($tax['rate'] ?? 0) / 100, 5);
                    array_unshift($taxes, ['type' => 'T1', 'subtype' => $tax['subtype'] ?? 'V009', 'rate' => $tax['rate'] ?? null, 'amount' => $amount]);
                }
            }

            $lineTotal = $lineNet;

            foreach ($taxes as $tax) {
                $lineTotal += $tax['type'] === 'T4' ? -$tax['amount'] : $tax['amount'];
                $taxTotals[$tax['type']] = ($taxTotals[$tax['type']] ?? 0) + $tax['amount'];
            }

            $data['lines'][$i] += ['sales' => round($lineSales, 2), 'net' => round($lineNet, 2), 'total' => round($lineTotal, 2)];
            $data['lines'][$i]['taxes'] = $taxes;
            $data['lines'][$i]['vat'] = round(array_sum(array_map(fn ($t) => $t['type'] === 'T1' ? $t['amount'] : 0, $taxes)), 2);

            $sales += $lineSales;
            $discounts += $lineDiscount;
            $net += $lineNet;
            $total += $lineTotal;
        }

        ksort($taxTotals, SORT_NATURAL);
        $extra = (float) ($data['extra_discount'] ?? 0);

        $data['totals'] = [
            'sales' => round($sales, 2),
            'discount' => round($discounts, 2),
            'net' => round($net, 2),
            'taxes' => array_map(fn ($amount) => round($amount, 2), $taxTotals),
            'extra_discount' => round($extra, 2),
            'total' => round($total - $extra, 2),
        ];

        $uuid = $data['document']['uuid'] ?? null;
        $longId = $data['document']['long_id'] ?? null;
        $data['qr'] ??= $uuid && $longId ? "https://invoicing.eta.gov.eg/documents/{$uuid}/share/{$longId}" : null;

        return $data;
    },

    'sample' => [
        'document' => [
            'type' => 'I',
            'internal_id' => 'INV-2026-1024',
            'issued_at' => '2026-10-08 11:45',
            'uuid' => 'R6ZQ4SB1ZWP2XKCV2G0AYXHG10',
            'long_id' => 'A3MZ7X0NE6C8XH3GV2G0AYXHG10QN3K91653372781',
            'currency' => 'EGP',
            'purchase_order' => 'PO-7781',
        ],
        'issuer' => [
            'name' => 'شركة بيزتك للحلول البرمجية',
            'rin' => '123456789',
            'branch_id' => '0',
            'activity_code' => '6201',
            'address' => '15 شارع التحرير، الدقي، الجيزة',
        ],
        'receiver' => [
            'type' => 'B',
            'id' => '987654321',
            'name' => 'مؤسسة النور للتجارة',
            'address' => 'مدينة نصر، القاهرة',
        ],
        'lines' => [
            [
                'description' => 'تطوير نظام إدارة المخزون',
                'item_type' => 'EGS', 'item_code' => 'EG-123456789-1001', 'unit' => 'EA',
                'quantity' => 1, 'unit_price' => 25000,
                'taxes' => [['type' => 'T1', 'rate' => 14], ['type' => 'T4', 'subtype' => 'W010', 'rate' => 3]],
            ],
            [
                'description' => 'استضافة سحابية - اشتراك شهري',
                'item_type' => 'EGS', 'item_code' => 'EG-123456789-2002', 'unit' => 'MON',
                'quantity' => 12, 'unit_price' => 450, 'discount' => 400,
                'taxes' => [['type' => 'T1', 'rate' => 14]],
            ],
        ],
    ],
];
