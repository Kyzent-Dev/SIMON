{{-- Kendala text with "Selengkapnya" toggle (>70 chars). $r = a MonitoringSpt row with a non-null kendala; $pre = unique id prefix (table and mobile card coexist on Rekap). --}}
<div x-data="{ lengkap: false }">
    <p id="{{ $pre ?? 'kendala' }}-{{ $r['id'] }}" class="line-clamp-2" x-bind:class="lengkap && 'line-clamp-none'">
        <span class="font-medium">{{ $r['kendala']['kategori'] }}:</span> {{ $r['kendala']['uraian'] }}
    </p>
    @if (mb_strlen($r['kendala']['kategori'].$r['kendala']['uraian']) > 70)
        <button type="button" x-on:click="lengkap = !lengkap" x-bind:aria-expanded="lengkap.toString()" aria-expanded="false" aria-controls="{{ $pre ?? 'kendala' }}-{{ $r['id'] }}"
            class="min-h-11 rounded-sm text-sm font-medium text-mint-700 hover:underline lg:min-h-6" x-text="lengkap ? 'Sembunyikan' : 'Selengkapnya'">Selengkapnya</button>
    @endif
</div>
