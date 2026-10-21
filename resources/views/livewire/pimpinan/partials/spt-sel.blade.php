{{-- SPT cells Kesesuaian · Status (+ Terlambat flag) · Permasalahan/Kendala ("Selengkapnya" toggle), shared by Monitoring and Rekap Detail Realisasi. $r = a MonitoringSpt row. --}}
<td class="whitespace-nowrap">
    @if ($r['kesesuaian'] === 'sesuai')
        <span class="inline-flex items-center gap-1 text-neutral-900">{{ svg('heroicon-m-check', 'size-4 text-neutral-600', ['aria-hidden' => 'true']) }}Sesuai</span>
    @elseif ($r['kesesuaian'] === 'tidak_sesuai')
        <x-app.flag jenis="tidak_sesuai_pkpt" />
    @else
        <span class="text-neutral-600">Non-PKPT</span>
    @endif
</td>
<td>
    <span class="flex flex-wrap items-center gap-1.5">
        <x-app.status-badge :status="$r['status']" />
        @if (in_array('terlambat', $r['penanda'], true))
            <x-app.flag jenis="terlambat" />
        @endif
    </span>
</td>
<td class="max-w-72 min-w-48 py-2">
    @if ($r['kendala'])
        <div x-data="{ lengkap: false }">
            <p id="kendala-{{ $r['id'] }}" class="line-clamp-2" x-bind:class="lengkap && 'line-clamp-none'">
                <span class="font-medium">{{ $r['kendala']['kategori'] }}:</span> {{ $r['kendala']['uraian'] }}
            </p>
            @if (mb_strlen($r['kendala']['kategori'].$r['kendala']['uraian']) > 70)
                <button type="button" x-on:click="lengkap = !lengkap" x-bind:aria-expanded="lengkap.toString()" aria-expanded="false" aria-controls="kendala-{{ $r['id'] }}"
                    class="min-h-11 rounded-sm text-sm font-medium text-mint-700 hover:underline lg:min-h-6" x-text="lengkap ? 'Sembunyikan' : 'Selengkapnya'">Selengkapnya</button>
            @endif
        </div>
    @else
        <span class="text-neutral-600">–</span>
    @endif
</td>
