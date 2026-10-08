@php($company = (array) $doc->theme('company', []))
@php($contact = array_values(array_filter([
    $company['address'] ?? null,
    ! empty($company['phone']) ? $doc->ltr($company['phone']) : null,
    ! empty($company['email']) ? $doc->ltr($company['email']) : null,
])))
<table style="width: 100%; font-size: 8pt; color: #6B7280; border-top: 1px solid #E5E7EB;">
    <tr>
        <td style="text-align: {{ $doc->start() }}; padding-top: 2mm;">
            @foreach ($contact as $part){{ $part }}@if (! $loop->last) | @endif @endforeach
        </td>
        <td style="text-align: {{ $doc->end() }}; padding-top: 2mm; width: 25%;">{{ $doc->t('page') }} {page} {{ $doc->t('of') }} {pages}</td>
    </tr>
</table>
