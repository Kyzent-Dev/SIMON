{{-- Register cell text clamped to 2 lines with a "Selengkapnya" toggle that shows only when the text really overflows (same measuring as
     pimpinan/partials/kendala). $teks = cell text or null ("–"); $id = unique element id. Before Alpine runs, the length guess (>60 characters) decides. --}}
@if ($teks === null)
    {{ \App\Services\RegisterNomor::KOSONG }}
@else
    @php $panjang = mb_strlen($teks) > 60; @endphp
    <div wire:key="{{ $id }}-{{ md5($teks) }}" class="min-w-48 max-w-64"
        x-data="{ lengkap: false, meluap: @js($panjang),
            ukur() { const p = this.$refs.teks; if (this.lengkap || !p.clientHeight) return; this.meluap = p.scrollHeight > p.clientHeight + 1; },
            init() { this.ukur(); this.$el._ro = new ResizeObserver(() => this.ukur()); this.$el._ro.observe(this.$refs.teks); },
            destroy() { this.$el._ro?.disconnect(); } }">
        <p x-ref="teks" id="{{ $id }}" class="line-clamp-2 whitespace-pre-line [overflow-wrap:anywhere]" x-bind:class="lengkap && 'line-clamp-none'">{{ $teks }}</p>
        <button type="button" x-on:click="lengkap = !lengkap" x-bind:aria-expanded="lengkap.toString()" aria-expanded="false" aria-controls="{{ $id }}"
            @unless ($panjang) style="display: none" @endunless x-show="meluap || lengkap"
            class="min-h-11 rounded-sm text-sm font-medium text-mint-700 hover:underline lg:min-h-6" x-text="lengkap ? 'Sembunyikan' : 'Selengkapnya'">Selengkapnya</button>
    </div>
@endif
