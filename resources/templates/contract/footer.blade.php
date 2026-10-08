@php
    // "First party initials" boxes, for the parties to sign every page.
    $ordinal = function (int $n) use ($doc) {
        $word = $doc->t('ordinals.'.$n);

        return $word === 'ordinals.'.$n ? (string) $n : $word;
    };
    $label = fn (int $n) => $doc->t('party', ['ordinal' => $ordinal($n), 'Ordinal' => ucfirst($ordinal($n))]);
@endphp
<table style="width: 100%; font-size: 8pt; color: #6B7280;">
    <tr>
        @foreach (array_slice($parties, 0, 3) as $i => $party)
            <td style="text-align: {{ $doc->start() }}; padding-bottom: 2mm;">{{ $doc->t('initials', ['party' => $label($i + 1), 'Party' => ucfirst($label($i + 1))]) }}: ....................</td>
        @endforeach
    </tr>
</table>
<table style="width: 100%; font-size: 8pt; color: #6B7280; border-top: 1px solid #E5E7EB;">
    <tr>
        <td style="text-align: {{ $doc->start() }}; padding-top: 2mm;">{{ $contract['title'] }}@if (! empty($contract['number'])) {{ $doc->ltr($contract['number']) }}@endif</td>
        <td style="text-align: {{ $doc->end() }}; padding-top: 2mm;">{{ $doc->t('page') }} {page} {{ $doc->t('of') }} {pages}</td>
    </tr>
</table>
