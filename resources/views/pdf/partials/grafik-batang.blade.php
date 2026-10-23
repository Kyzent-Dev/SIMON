{{-- Print-only bar chart in plain tables/divs (dompdf runs no JS, no motion). Props: $kode, $jenis (berpasangan|bertumpuk|persen), $judul, $baris [{label, nilai, teks?}], $legenda [{label, isi, garis}].
     Bar width = value / chart maximum (persen: of 100 at least); the number sits after the bar, always in TEKS. --}}
@php
    $total = fn ($b) => $jenis === 'bertumpuk' ? array_sum($b['nilai']) : max($b['nilai'] ?: [0]);
    $maks = max([$jenis === 'persen' ? 100 : 0, ...array_map($total, $baris)]);
    $pct = fn ($n) => $maks > 0 ? round($n / $maks * 100, 1) : 0;
    $warna = fn ($i) => $legenda[$jenis === 'persen' ? 0 : $i];
@endphp
<div class="blok" data-grafik="{{ $kode }}">
    <h3>{{ $judul }}</h3>
    <p class="legenda">@foreach ($legenda as $l)<span style="background: {{ $l['isi'] }}; border: 1px solid {{ $l['garis'] }};">&nbsp;&nbsp;&nbsp;&nbsp;</span> {{ $l['label'] }}&nbsp;&nbsp;&nbsp;@endforeach</p>
    <table class="grafik-tabel">
        @foreach ($baris as $b)
            <tr>
                <td class="grafik-label">{{ $b['label'] }}</td>
                <td>
                    @if ($jenis === 'bertumpuk')
                        @php($t = $total($b))
                        <table class="grafik-bar"><tr>
                            <td class="grafik-area">
                                <div style="width: {{ $pct($t) }}%;">
                                    @if ($t > 0)
                                        <table class="grafik-bar"><tr>
                                            @foreach ($b['nilai'] as $i => $n)
                                                @if ($n > 0)<td style="width: {{ round($n / $t * 100, 1) }}%; height: 10px; background: {{ $warna($i)['isi'] }}; border: 1px solid {{ $warna($i)['garis'] }};"></td>@endif
                                            @endforeach
                                        </tr></table>
                                    @endif
                                </div>
                            </td>
                            <td class="grafik-angka">{{ $b['teks'] ?? $t }}</td>
                        </tr></table>
                    @else
                        @foreach ($b['nilai'] as $i => $n)
                            <table class="grafik-bar"><tr>
                                <td class="grafik-area"><div style="width: {{ $pct($n) }}%; height: 10px; background: {{ $warna($i)['isi'] }}; border: 1px solid {{ $warna($i)['garis'] }};"></div></td>
                                <td class="grafik-angka">{{ $b['teks'] ?? $n }}</td>
                            </tr></table>
                        @endforeach
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</div>
