@php
    $company = (array) $doc->theme('company', []);
    $primary = $doc->theme('primary', '#0F766E');
    $logo = $doc->image($doc->theme('logo'));
    $signatureImage = $doc->image($signature ?? null);
    $stampImage = $doc->image($stamp ?? null);
    $date = \Illuminate\Support\Carbon::parse($date);
@endphp
<x-doc::layout :doc="$doc" :title="$subject">
    <x-slot:styles>
        <style>
            .letterhead { border-bottom: 2px solid {{ $primary }}; padding-bottom: 3mm; }
            .company { font-size: 14pt; font-weight: bold; color: {{ $primary }}; }
            .ref td { font-size: 9.5pt; padding: 0.8mm 0; text-align: {{ $doc->start() }}; }
            .ref .label { color: {{ $doc->theme('muted') }}; width: 26mm; }
            .subject { text-align: center; font-size: 12.5pt; font-weight: bold; margin: 8mm 0 6mm 0; }
            .body p { text-align: justify; line-height: 1.9; margin-bottom: 4mm; font-size: 11pt; }
            .signature { text-align: center; }
        </style>
    </x-slot:styles>

    <table class="letterhead">
        <tr>
            <td style="width: 60%;">
                <div class="company">{{ $company['name'] ?? '' }}</div>
                @if (! empty($company['address']))<div class="muted">{{ $company['address'] }}</div>@endif
            </td>
            <td style="width: 40%; text-align: {{ $doc->end() }};">
                @if ($logo)
                    <img src="{{ $logo }}" style="height: 18mm;">
                @endif
            </td>
        </tr>
    </table>

    <table style="margin-top: 5mm;">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%;">
                <table class="ref">
                    @if (! empty($reference))
                        <tr><td class="label">{{ $doc->t('reference') }}</td><td>{{ $reference }}</td></tr>
                    @endif
                    <tr><td class="label">{{ $doc->t('date') }}</td><td>{{ $date->format('Y/m/d') }}</td></tr>
                    @if (($show_hijri ?? true) && $doc->isRtl() && $doc->hasHijri())
                        <tr><td class="label">{{ $doc->t('hijri') }}</td><td>{{ $doc->hijri($date) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div style="margin-top: 6mm;">
        @if (! empty($recipient['title']))<div>{{ $doc->t('to_title', ['title' => $recipient['title']]) }}</div>@endif
        <div style="font-weight: bold; font-size: 11.5pt;">{{ $recipient['name'] }}</div>
        @if (! empty($recipient['organization']))<div>{{ $recipient['organization'] }}</div>@endif
    </div>

    <div class="subject">{{ $doc->t('subject') }}: {{ $subject }}</div>

    <div>{{ $greeting ?? $doc->t('greeting') }}</div>

    <div class="body" style="margin-top: 4mm;">
        @foreach ($paragraphs as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
    </div>

    <div style="margin-top: 2mm;">{{ $closing ?? $doc->t('closing') }}</div>

    <table style="margin-top: 10mm;">
        <tr>
            <td style="width: 55%;">
                @if ($stampImage)
                    <img src="{{ $stampImage }}" style="height: 30mm;">
                @endif
            </td>
            <td class="signature" style="width: 45%;">
                @if (! empty($sender['title']))<div>{{ $sender['title'] }}</div>@endif
                @if ($signatureImage)
                    <img src="{{ $signatureImage }}" style="height: 18mm; margin: 2mm 0;">
                @else
                    <div style="height: 16mm;"></div>
                @endif
                <div style="font-weight: bold;">{{ $sender['name'] }}</div>
            </td>
        </tr>
    </table>

    @if (! empty($cc))
        <div style="margin-top: 10mm; font-size: 9.5pt;">
            <div class="muted">{{ $doc->t('cc') }}:</div>
            @foreach ($cc as $copy)
                <div>- {{ $copy }}</div>
            @endforeach
        </div>
    @endif
</x-doc::layout>
