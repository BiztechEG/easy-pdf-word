@php
    $primary = $doc->theme('primary', '#0F766E');
    $muted = $doc->theme('muted', '#6B7280');
    $logo = $doc->image($doc->theme('logo'));
    $format = fn ($value) => \Illuminate\Support\Carbon::parse($value)->format('Y/m/d');

    // "خلال الفترة من ... إلى ...، بعدد 40 ساعة تدريبية، بتقدير امتياز"
    $facts = array_values(array_filter([
        ! empty($from) && ! empty($to) ? $doc->t('period', ['from' => $format($from), 'to' => $format($to)]) : (($day = ($from ?? null) ?: ($to ?? null)) ? $doc->t('on', ['date' => $format($day)]) : null),
        ! empty($hours_form) ? $doc->t('hours.'.$hours_form, ['hours' => $doc->rate($hours)]) : null,
        ! empty($grade) ? $doc->t('grade', ['grade' => $grade]) : null,
    ]));
    $signatures = array_values($signatures ?? []);
@endphp
<x-doc::layout :doc="$doc" :title="$doc->t('title.'.$type)">
    <x-slot:styles>
        <style>
            .frame { border: 2.2mm double {{ $primary }}; padding: 2mm; }
            .inner { border: 0.3mm solid {{ $primary }}; height: 170mm; padding: 7mm 12mm 0 12mm; text-align: center; }
            .corner td { font-size: 8.5pt; color: {{ $muted }}; vertical-align: top; }
            .issuer { font-size: 14pt; font-weight: bold; }
            .title { font-size: 34pt; font-weight: bold; color: {{ $primary }}; margin: 6mm 0 7mm 0; line-height: 1.2; }
            .intro, .statement { font-size: 14pt; }
            .recipient { font-size: 30pt; font-weight: bold; margin: 3mm 0; line-height: 1.3; }
            .rule { width: 40%; margin: 0 auto 4mm auto; border-top: 0.4mm solid {{ $primary }}; height: 0; }
            .course { font-size: 19pt; font-weight: bold; color: {{ $primary }}; margin: 3mm 0 4mm 0; line-height: 1.4; }
            .details { font-size: 11.5pt; color: {{ $muted }}; }
            .signatures td { text-align: center; font-size: 10.5pt; }
            .signatures .line { border-bottom: 0.3mm solid {{ $muted }}; height: 14mm; }
        </style>
    </x-slot:styles>

    <div class="frame">
        <div class="inner">
            {{-- Date and number in one corner, the logo and issuer in the middle, the QR code in the other corner. --}}
            <table class="corner">
                <tr>
                    <td style="width: 25%; text-align: {{ $doc->start() }};">
                        <div>{{ $doc->t('date') }}: {{ $format($date) }}</div>
                        @if (! empty($number))
                            <div>{{ $doc->t('number') }}: {{ $doc->ltr($number) }}</div>
                        @endif
                    </td>
                    <td style="width: 50%; text-align: center;">
                        @if ($logo)
                            <img src="{{ $logo }}" style="height: 16mm;">
                        @endif
                        @if (! empty($issuer))
                            <div class="issuer">{{ $issuer }}</div>
                        @endif
                    </td>
                    <td style="width: 25%; text-align: {{ $doc->end() }};">
                        @if (! empty($verify_url))
                            <img src="{{ \BiztechEG\EasyPdfWord\Support\Qr::dataUri($verify_url) }}" style="width: 20mm;">
                            <div style="font-size: 7pt;">{{ $doc->t('verify') }}</div>
                        @endif
                    </td>
                </tr>
            </table>

            <div class="title">{{ $doc->t('title.'.$type) }}</div>
            <div class="intro">{{ $doc->t('intro.'.$type) }}</div>
            <div class="recipient">{{ $recipient }}</div>
            <div class="rule"></div>
            <div class="statement">{{ $doc->t('statement.'.$type.'.'.$gender) }}</div>
            <div class="course">{{ $course }}</div>

            @if ($facts !== [])
                <div class="details">{{ implode($doc->isRtl() ? '، ' : ', ', $facts) }}</div>
            @endif
            @if (! empty($details))
                <div class="details">{{ $details }}</div>
            @endif

            {{-- Signatures: a line to sign on, then the name and title, with gaps between them. --}}
            @if ($signatures !== [])
                @php
                    $width = count($signatures) === 3 ? 26 : 30;
                    $gap = round((100 - count($signatures) * $width) / (count($signatures) + 1), 2);
                @endphp
                <table class="signatures" style="margin-top: 12mm;">
                    <tr>
                        @foreach ($signatures as $signature)
                            <td style="width: {{ $gap }}%;"></td>
                            <td class="line" style="width: {{ $width }}%;"></td>
                        @endforeach
                        <td style="width: {{ $gap }}%;"></td>
                    </tr>
                    <tr>
                        @foreach ($signatures as $signature)
                            <td></td>
                            <td style="padding-top: 1.5mm;">
                                <div style="font-weight: bold;">{{ $signature['name'] }}</div>
                                @if (! empty($signature['title']))
                                    <div class="muted">{{ $signature['title'] }}</div>
                                @endif
                            </td>
                        @endforeach
                        <td></td>
                    </tr>
                </table>
            @endif
        </div>
    </div>
</x-doc::layout>
