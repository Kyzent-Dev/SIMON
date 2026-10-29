{{-- Print-only bar charts in plain tables/divs (dompdf runs no JS, no motion). Props: $kode, $jenis (berpasangan|bertumpuk|persen), $judul, $baris [{label, nilai, teks?}], $legenda [{label, isi, garis}].
     berpasangan/bertumpuk: vertical bars (Y axis, gridlines, value above, label below), heights from AppSupportSkalaGrafik. persen: horizontal bars, width = value / 100 at least; the number sits after the bar, always in TEKS. --}}
@php
    $vertikal = $jenis !== 'persen';
    $total = fn ($b) => $jenis === 'bertumpuk' ? array_sum($b['nilai']) : max($b['nilai'] ?: [0]);
    $maks = max([$jenis === 'persen' ? 100 : 0, ...array_map($total, $baris)]);
    $pct = fn ($n) => $maks > 0 ? round($n / $maks * 100, 1) : 0;
    $warna = fn ($i) => $legenda[$jenis === 'persen' ? 0 : $i];
    if ($vertikal) {
        $tick = \App\Support\SkalaGrafik::tick($maks);
        $garis = \App\Support\SkalaGrafik::garis($tick);
        $puncak = end($tick);
        $tinggi = \App\Support\SkalaGrafik::TINGGI;
        $lebar = \App\Support\SkalaGrafik::lebar(count($baris));
    }
@endphp
<div class="blok" data-grafik="{{ $kode }}">
    <h3>{{ $judul }}</h3>
    @if ($vertikal)
        <div class="grafik-v" style="width: {{ $lebar }}%;">
            <div class="grafik-plot" style="height: {{ $tinggi + 14 }}px;">
                @foreach ($garis as $g)
                    <div class="grafik-garis" style="bottom: {{ $g['bawah'] }}px;"></div>
                    <div class="grafik-y" style="bottom: {{ $g['bawah'] - 5 }}px;">{{ $g['nilai'] }}</div>
                @endforeach
                <table class="grafik-kolom"><tr>
                    @foreach ($baris as $b)
                        @php($h = \App\Support\SkalaGrafik::batang($b['nilai'], $puncak, $jenis === 'bertumpuk', $tinggi))
                        <td valign="bottom" style="vertical-align: bottom; height: {{ $tinggi + 14 }}px;">
                            @if ($jenis === 'bertumpuk')
                                <div class="grafik-nilai">{{ $total($b) }}</div>
                                {{-- Stack: last series on top. A zero segment is left out (its border would be a sliver). --}}
                                <table class="grafik-tumpuk"><tr><td valign="bottom">
                                    @foreach (array_reverse(array_keys($b['nilai'])) as $i)
                                        @if ($h[$i] > 0)<div class="grafik-batang" style="height: {{ $h[$i] - 1 }}px; background: {{ $warna($i)['isi'] }}; border-color: {{ $warna($i)['garis'] }};"></div>@endif
                                    @endforeach
                                </td></tr></table>
                            @else
                                <table class="grafik-pasang"><tr valign="bottom">
                                    @foreach ($b['nilai'] as $i => $n)
                                        <td valign="bottom">
                                            <div class="grafik-nilai">{{ $n }}</div>
                                            @if ($h[$i] > 0)<div class="grafik-batang" style="height: {{ $h[$i] - 1 }}px; background: {{ $warna($i)['isi'] }}; border-color: {{ $warna($i)['garis'] }};"></div>@endif
                                        </td>
                                    @endforeach
                                </tr></table>
                            @endif
                        </td>
                    @endforeach
                </tr></table>
            </div>
            <table class="grafik-kolom grafik-kat"><tr>@foreach ($baris as $b)<td>{{ $b['label'] }}</td>@endforeach</tr></table>
        </div>
    @endif
    <p class="legenda"@if ($vertikal) style="text-align: center; margin-top: 6px;"@endif>@foreach ($legenda as $l)<span class="titik" style="background: {{ $l['isi'] }}; border: 1px solid {{ $l['garis'] }};"></span> {{ $l['label'] }}&nbsp;&nbsp;&nbsp;@endforeach</p>
    @unless ($vertikal)
    <table class="grafik-tabel">
        @foreach ($baris as $b)
            <tr>
                <td class="grafik-label">{{ $b['label'] }}</td>
                <td>
                    @foreach ($b['nilai'] as $i => $n)
                        <table class="grafik-bar"><tr>
                            {{-- A zero draws no bar: its 1px border would show as a sliver. --}}
                            <td class="grafik-area">@if ($n > 0)<div style="width: {{ $pct($n) }}%; height: 10px; background: {{ $warna($i)['isi'] }}; border: 1px solid {{ $warna($i)['garis'] }};"></div>@endif</td>
                            <td class="grafik-angka">{{ is_array($b['teks'] ?? null) ? $b['teks'][$i] : ($b['teks'] ?? $n) }}</td>
                        </tr></table>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </table>
    @endunless
</div>
