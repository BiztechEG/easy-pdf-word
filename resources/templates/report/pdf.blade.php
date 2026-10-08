@php
    $primary = $doc->theme('primary', '#0F766E');
    $border = $doc->theme('border', '#E5E7EB');
    $company = (array) $doc->theme('company', []);
    $align = fn ($column) => match ($column['align']) {
        'end' => $doc->end(),
        'center' => 'center',
        default => $doc->start(),
    };
    $format = function ($column, $value) use ($doc) {
        return match ($column['format']) {
            'number', 'money' => $value === null || $value === '' ? '' : $doc->number($value, $column['decimals']),
            'date' => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y/m/d') : '',
            default => $value,
        };
    };
@endphp
<x-doc::layout :doc="$doc" :title="$title">
    <x-slot:styles>
        <style>
            .title { font-size: 17pt; font-weight: bold; color: {{ $primary }}; }
            .summary td.card { border: 1px solid {{ $border }}; padding: 3mm; }
            .summary .value { font-size: 12.5pt; font-weight: bold; color: {{ $primary }}; }
            .data th { background-color: {{ $primary }}; color: #FFFFFF; padding: 2.2mm 2mm; font-size: 9.5pt; }
            .data td { padding: 2mm; border-bottom: 1px solid {{ $border }}; font-size: 9.5pt; }
            .data tr.even td { background-color: #F9FAFB; }
            .data tr.total td { font-weight: bold; border-top: 2px solid {{ $primary }}; background-color: #F3F4F6; }
        </style>
    </x-slot:styles>

    <table>
        <tr>
            <td style="width: 65%;">
                <div class="title">{{ $title }}</div>
                @if (! empty($subtitle))<div class="muted">{{ $subtitle }}</div>@endif
            </td>
            <td style="width: 35%; text-align: {{ $doc->end() }};" class="muted">
                <div>{{ $company['name'] ?? '' }}</div>
                <div style="font-size: 9pt;">{{ $doc->t('generated_at') }}: {{ \Illuminate\Support\Carbon::parse($generated_at)->format('Y/m/d H:i') }}</div>
            </td>
        </tr>
    </table>

    @if (! empty($summary))
        <table class="summary" style="margin-top: 6mm;">
            <tr>
                @foreach ($summary as $label => $value)
                    <td class="card">
                        <div class="muted" style="font-size: 9pt;">{{ $label }}</div>
                        <div class="value">{{ $value }}</div>
                    </td>
                    @unless ($loop->last)<td style="width: 3mm;"></td>@endunless
                @endforeach
            </tr>
        </table>
    @endif

    <table class="data" style="margin-top: 6mm;">
        <thead>
            <tr>
                <th style="width: 7mm; text-align: {{ $doc->start() }};">#</th>
                @foreach ($columns as $column)
                    <th style="text-align: {{ $align($column) }};">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="{{ $loop->even ? 'even' : '' }}">
                    <td>{{ $loop->iteration }}</td>
                    @foreach ($columns as $column)
                        <td style="text-align: {{ $align($column) }};">{{ $format($column, data_get($row, $column['key'])) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}" class="text-center muted">{{ $doc->t('empty') }}</td></tr>
            @endforelse
            @if (! empty($totals))
                <tr class="total">
                    <td></td>
                    @foreach ($columns as $column)
                        <td style="text-align: {{ $align($column) }};">
                            @if (array_key_exists($column['key'], $totals))
                                {{ $format($column, $totals[$column['key']]) }}
                            @elseif ($loop->first)
                                {{ $doc->t('total') }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</x-doc::layout>
