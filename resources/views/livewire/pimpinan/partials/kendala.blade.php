{{-- Kendala text clamped to 2 lines; "Selengkapnya" shows only when the text really overflows (measured, re-measured when the box resizes or is shown).
     $r = a MonitoringSpt row with a non-null kendala; $pre = unique id prefix (table and mobile card coexist on Rekap). The wire:key carries the text, so a morph
     that changes it starts a fresh measurement. Before Alpine runs, the length guess (>70 characters) decides. --}}
@php $panjang = mb_strlen($r['kendala']['kategori'].$r['kendala']['uraian']) > 70; @endphp
<div wire:key="{{ $pre ?? 'kendala' }}-{{ $r['id'] }}-{{ md5($r['kendala']['kategori'].$r['kendala']['uraian']) }}"
    x-data="{ lengkap: false, meluap: @js($panjang),
        ukur() { const p = this.$refs.teks; if (this.lengkap || !p.clientHeight) return; this.meluap = p.scrollHeight > p.clientHeight + 1; },
        init() { this.ukur(); this.$el._ro = new ResizeObserver(() => this.ukur()); this.$el._ro.observe(this.$refs.teks); },
        destroy() { this.$el._ro?.disconnect(); } }">
    <p x-ref="teks" id="{{ $pre ?? 'kendala' }}-{{ $r['id'] }}" class="line-clamp-2" x-bind:class="lengkap && 'line-clamp-none'">
        <span class="font-medium">{{ $r['kendala']['kategori'] }}:</span> {{ $r['kendala']['uraian'] }}
    </p>
    <button type="button" x-on:click="lengkap = !lengkap" x-bind:aria-expanded="lengkap.toString()" aria-expanded="false" aria-controls="{{ $pre ?? 'kendala' }}-{{ $r['id'] }}"
        @unless ($panjang) style="display: none" @endunless x-show="meluap || lengkap"
        class="min-h-11 rounded-sm text-sm font-medium text-mint-700 hover:underline lg:min-h-6" x-text="lengkap ? 'Sembunyikan' : 'Selengkapnya'">Selengkapnya</button>
</div>
