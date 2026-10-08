@php
    // The month by name ("سبتمبر 2026"), so it does not run into the employee code in Arabic.
    $month = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $period)->locale($doc->locale)->translatedFormat('F Y');
@endphp
<table style="width: 100%; font-size: 8pt; color: #6B7280; border-top: 1px solid #E5E7EB;">
    <tr>
        <td style="text-align: {{ $doc->start() }}; padding-top: 2mm;">{{ $doc->t('confidential') }} - {{ $doc->t('title') }} {{ $month }}@if (! empty($employee['code'])) - {{ $doc->ltr($employee['code']) }}@endif</td>
        <td style="text-align: {{ $doc->end() }}; padding-top: 2mm;">{{ $doc->t('page') }} {page} {{ $doc->t('of') }} {pages}</td>
    </tr>
</table>
