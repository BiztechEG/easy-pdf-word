<?php

use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;
use BiztechEG\EasyPdfWord\Support\Currency;

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
| or a fixed amount: ['type' => 'T3', 'amount' => 50]. Computed as ETA
| does: fees (T5-T20) on the net amount; table tax T2 on the net plus T3
| and the taxable fees (T5-T12); VAT (T1) on the net plus T2, T3 and the
| taxable fees; withholding (T4) on the net, deducted. Non-taxable fees
| (T13-T20) are added to the total only.
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
        'lines.*.taxes.*.type' => ['required', 'regex:/^T([1-9]|1[0-9]|20)$/i'],
        'lines.*.taxes.*.subtype' => ['nullable', 'string', 'max:20'],
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
        // Adds the line's taxes of the given types, charged on $base, to $taxes; returns their sum.
        $charge = function (array $byType, array $types, float $base, array &$taxes): float {
            $charged = 0.0;

            foreach ($types as $type) {
                foreach ($byType[$type] ?? [] as $tax) {
                    $amount = round(isset($tax['amount']) ? (float) $tax['amount'] : $base * (float) ($tax['rate'] ?? 0) / 100, 5);
                    $taxes[] = ['type' => $type, 'subtype' => $tax['subtype'] ?? ($type === 'T1' ? 'V009' : null), 'rate' => $tax['rate'] ?? null, 'amount' => $amount];
                    $charged += $amount;
                }
            }

            return $charged;
        };

        $taxTotals = [];
        $sales = $discounts = $net = $total = 0.0;
        $decimals = Currency::decimals($data['document']['currency'] ?? null);

        // Numbered 1, 2, 3 ... whatever the keys (a filtered collection keeps its keys).
        $data['lines'] = array_values($data['lines']);

        foreach ($data['lines'] as $i => $line) {
            $lineSales = round((float) $line['quantity'] * (float) $line['unit_price'], 5);
            $lineDiscount = (float) ($line['discount'] ?? 0);

            if ($lineDiscount > $lineSales) {
                throw ValidationFailed::withMessages(["lines.{$i}.discount" => 'The discount cannot be more than the line amount (quantity × unit price).']);
            }

            $lineNet = $lineSales - $lineDiscount;
            $byType = [];

            foreach ($line['taxes'] ?? [] as $tax) {
                $byType[strtoupper($tax['type'])][] = $tax;
            }

            // ETA order: fees and fixed table tax on the net, table tax (T2)
            // on the net + T3 + taxable fees, VAT (T1) on all of those, and
            // withholding (T4) on the net.
            $taxes = [];
            $fees = $charge($byType, array_map(fn ($n) => 'T'.$n, range(5, 12)), $lineNet, $taxes);
            $charge($byType, array_map(fn ($n) => 'T'.$n, range(13, 20)), $lineNet, $taxes);
            $t3 = $charge($byType, ['T3'], $lineNet, $taxes);
            $t2 = $charge($byType, ['T2'], $lineNet + $t3 + $fees, $taxes);
            $vat = $charge($byType, ['T1'], $lineNet + $t2 + $t3 + $fees, $taxes);
            $charge($byType, ['T4'], $lineNet, $taxes);

            usort($taxes, fn ($a, $b) => strnatcmp($a['type'], $b['type']));
            $lineTotal = $lineNet;

            foreach ($taxes as $tax) {
                $lineTotal += $tax['type'] === 'T4' ? -$tax['amount'] : $tax['amount'];
                $taxTotals[$tax['type']] = ($taxTotals[$tax['type']] ?? 0) + $tax['amount'];
            }

            $data['lines'][$i] = array_replace($line, [
                'sales' => round($lineSales, $decimals),
                'net' => round($lineNet, $decimals),
                'total' => round($lineTotal, $decimals),
                'taxes' => $taxes,
                'vat' => round($vat, $decimals),
            ]);

            $sales += $lineSales;
            $discounts += $lineDiscount;
            $net += $lineNet;
            $total += $lineTotal;
        }

        ksort($taxTotals, SORT_NATURAL);
        $extra = (float) ($data['extra_discount'] ?? 0);

        $data['totals'] = [
            'sales' => round($sales, $decimals),
            'discount' => round($discounts, $decimals),
            'net' => round($net, $decimals),
            'taxes' => array_map(fn ($amount) => round($amount, $decimals), $taxTotals),
            'extra_discount' => round($extra, $decimals),
            'total' => round($total - $extra, $decimals),
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
