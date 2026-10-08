@php
    $seller = $seller ?? [];
    $primary = $doc->theme('primary', '#0F766E');
    $border = $doc->theme('border', '#E5E7EB');
    $currency = $invoice['currency'];
    $currencyLabel = $doc->t('currencies.'.$currency) === 'currencies.'.$currency ? $currency : $doc->t('currencies.'.$currency);
    $logo = $doc->image($doc->theme('logo'));
@endphp
<x-doc::layout :doc="$doc" :title="$doc->t('title').' '.$invoice['number']">
    <x-slot:styles>
        <style>
            .head td { padding: 0; }
            .brand { font-size: 15pt; font-weight: bold; color: {{ $primary }}; }
            .doc-title { font-size: 20pt; font-weight: bold; color: {{ $primary }}; }
            .meta td { padding: 1.5pt 0; font-size: 9.5pt; text-align: {{ $doc->start() }}; }
            .meta .label { color: {{ $doc->theme('muted') }}; width: 32mm; }
            .party { border: 1px solid {{ $border }}; padding: 3mm 4mm; }
            .party-title { font-size: 9pt; color: {{ $doc->theme('muted') }}; margin-bottom: 1mm; }
            .party-name { font-size: 11.5pt; font-weight: bold; }
            .items th { background-color: {{ $primary }}; color: #FFFFFF; font-size: 9.5pt; padding: 2.5mm 2mm; text-align: {{ $doc->start() }}; }
            .items td { border-bottom: 1px solid {{ $border }}; padding: 2.5mm 2mm; font-size: 10pt; }
            .items tr.even td { background-color: #F9FAFB; }
            .num, .items th.num { text-align: {{ $doc->end() }}; white-space: nowrap; }
            .totals td { padding: 1.8mm 2mm; font-size: 10pt; }
            .totals .grand td { background-color: {{ $primary }}; color: #FFFFFF; font-weight: bold; font-size: 11.5pt; }
            .words { border: 1px dashed {{ $primary }}; padding: 3mm 4mm; font-size: 10pt; }
        </style>
    </x-slot:styles>

    <table class="head">
        <tr>
            <td style="width: 55%;">
                @if ($logo)
                    <img src="{{ $logo }}" style="height: 16mm; margin-bottom: 2mm;"><br>
                @endif
                <div class="brand">{{ $seller['name'] ?? '' }}</div>
                @if (! empty($seller['address']))<div class="muted">{{ $seller['address'] }}</div>@endif
                @if (! empty($seller['phone']))<div class="muted">{{ $doc->ltr($seller['phone']) }}</div>@endif
            </td>
            <td style="width: 45%;">
                <div class="doc-title">{{ $doc->t('title') }}</div>
                <table class="meta">
                    <tr><td class="label">{{ $doc->t('number') }}</td><td>{{ $doc->ltr($invoice['number']) }}</td></tr>
                    <tr><td class="label">{{ $doc->t('date') }}</td><td>{{ \Illuminate\Support\Carbon::parse($invoice['date'])->format('Y/m/d') }}</td></tr>
                    @if ($doc->isRtl())
                        <tr><td class="label">{{ $doc->t('hijri_date') }}</td><td>{{ $doc->hijri($invoice['date']) }}</td></tr>
                    @endif
                    @if (! empty($invoice['due_date']))
                        <tr><td class="label">{{ $doc->t('due_date') }}</td><td>{{ \Illuminate\Support\Carbon::parse($invoice['due_date'])->format('Y/m/d') }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 7mm;">
        <tr>
            <td class="party" style="width: 49%;">
                <div class="party-title">{{ $doc->t('seller') }}</div>
                <div class="party-name">{{ $seller['name'] ?? '' }}</div>
                @if (! empty($seller['tax_number']))<div>{{ $doc->t('tax_number') }}: {{ $doc->ltr($seller['tax_number']) }}</div>@endif
                @if (! empty($seller['commercial_register']))<div>{{ $doc->t('commercial_register') }}: {{ $doc->ltr($seller['commercial_register']) }}</div>@endif
            </td>
            <td style="width: 2%;"></td>
            <td class="party" style="width: 49%;">
                <div class="party-title">{{ $doc->t('buyer') }}</div>
                <div class="party-name">{{ $buyer['name'] }}</div>
                @if (! empty($buyer['address']))<div>{{ $buyer['address'] }}</div>@endif
                @if (! empty($buyer['tax_number']))<div>{{ $doc->t('tax_number') }}: {{ $doc->ltr($buyer['tax_number']) }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 7mm;">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th>{{ $doc->t('description') }}</th>
                <th class="num" style="width: 10%;">{{ $doc->t('quantity') }}</th>
                <th class="num" style="width: 15%;">{{ $doc->t('unit_price') }}</th>
                <th class="num" style="width: 13%;">{{ $doc->t('discount') }}</th>
                <th class="num" style="width: 16%;">{{ $doc->t('line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr class="{{ $loop->even ? 'even' : '' }}">
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item['description'] }}</td>
                    <td class="num">{{ $doc->number($item['quantity'], floor($item['quantity']) == $item['quantity'] ? 0 : 2) }}</td>
                    <td class="num">{{ $doc->number($item['unit_price']) }}</td>
                    <td class="num">{{ ! empty($item['discount']) ? $doc->number($item['discount']) : '-' }}</td>
                    <td class="num">{{ $doc->number($item['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 5mm;">
        <tr>
            <td style="width: 52%; vertical-align: top;">
                @if (! empty($qr))
                    <x-doc::qr :value="$qr" size="30mm" />
                    @if (! empty($invoice['eta_uuid']))
                        <div class="muted" style="font-size: 8pt;">{{ $doc->t('eta_uuid') }}: {{ $doc->ltr($invoice['eta_uuid']) }}</div>
                    @endif
                @endif
            </td>
            <td style="width: 48%;">
                <table class="totals">
                    <tr><td>{{ $doc->t('subtotal') }}</td><td class="num">{{ $doc->number($totals['subtotal']) }}</td></tr>
                    @if ($totals['discount'] > 0)
                        <tr><td>{{ $doc->t('discount') }}</td><td class="num">- {{ $doc->number($totals['discount']) }}</td></tr>
                    @endif
                    <tr><td>{{ $doc->t('vat', ['rate' => $doc->number($invoice['tax_rate'], 0)]) }}</td><td class="num">{{ $doc->number($totals['tax']) }}</td></tr>
                    <tr class="grand"><td>{{ $doc->t('total') }}</td><td class="num">{{ $doc->number($totals['total']) }} {{ $currencyLabel }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($doc->isRtl())
        <div class="words" style="margin-top: 5mm;">
            <strong>{{ $doc->t('amount_in_words') }}:</strong> {{ $doc->tafqeet($totals['total'], $currency) }}
        </div>
    @endif

    @if (! empty($invoice['notes']))
        <div style="margin-top: 6mm;">
            <strong>{{ $doc->t('notes') }}</strong>
            <p class="muted">{{ $invoice['notes'] }}</p>
        </div>
    @endif
</x-doc::layout>
