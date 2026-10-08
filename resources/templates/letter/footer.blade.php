@php($company = (array) $doc->theme('company', []))
<table style="width: 100%; font-size: 8pt; color: #6B7280; border-top: 1px solid #E5E7EB;">
    <tr>
        <td style="text-align: {{ $doc->start() }}; padding-top: 2mm;">
            {{ collect([$company['address'] ?? null, $company['phone'] ?? null, $company['email'] ?? null])->filter()->implode(' | ') }}
        </td>
        <td style="text-align: {{ $doc->end() }}; padding-top: 2mm; width: 25%;">{{ $doc->t('page') }} {page} {{ $doc->t('of') }} {pages}</td>
    </tr>
</table>
