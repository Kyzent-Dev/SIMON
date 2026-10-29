{{-- _shared.md §6 split button "Ekspor": the main button exports Excel, the chevron opens a menu with "Excel (.xlsx)" and "PDF"; subtext "Mengikuti filter aktif".
     Calls the host's `{$aksi}('xlsx'|'pdf')` (default `ekspor`; Rekap passes `bukaEkspor` to open its section picker) (App\Services\Ekspor: direct download up to its threshold, queued with a notification above it). Menu: Esc/outside click close, focus trap, arrow keys move between items. No animation.
     Optional slot `tambahan`: extra `role="menuitem"` buttons below the caption, for exports that do not follow the active filters (Register: Rekap Tahunan). --}}
@props(['label' => 'Ekspor', 'aksi' => 'ekspor'])

<div x-data="{ open: false, top: 0, left: 0,
        show() { const r = this.$refs.pemicu.getBoundingClientRect(); this.top = r.bottom + 4; this.left = Math.max(8, Math.min(r.right - 224, window.innerWidth - 232)); this.open = true; this.$nextTick(() => this.$refs.menu.querySelector('[role=menuitem]')?.focus()) },
        tutup(fokus = false) { this.open = false; if (fokus) this.$nextTick(() => this.$refs.pemicu.focus()) },
        geser(arah) { const i = [...this.$refs.menu.querySelectorAll('[role=menuitem]')]; const n = i.indexOf(document.activeElement); i[(n + arah + i.length) % i.length]?.focus() },
    }" x-on:keydown.escape.window="if (open) tutup(true)" x-on:resize.window="open = false" {{ $attributes->class('inline-flex flex-col') }}>
    <div class="inline-flex">
        <button type="button" data-ekspor wire:click="{{ $aksi }}('xlsx')" wire:loading.attr="disabled" wire:target="{{ $aksi }}"
            class="inline-flex h-11 items-center gap-2 rounded-l-md border border-border-strong bg-white px-4 text-sm font-medium text-mint-700 hover:bg-mint-50 disabled:cursor-not-allowed disabled:text-neutral-400 lg:h-10">
            {{ svg('heroicon-m-arrow-down-tray', 'size-5') }}<span wire:loading.remove wire:target="{{ $aksi }}">{{ $label }}</span><span wire:loading wire:target="{{ $aksi }}">Memproses…</span>
        </button>
        <button type="button" x-ref="pemicu" x-on:click="open ? tutup() : show()" aria-haspopup="menu" aria-label="Pilih format ekspor" x-bind:aria-expanded="open.toString()" aria-expanded="false"
            class="-ml-px inline-flex size-11 items-center justify-center rounded-r-md border border-border-strong bg-white text-mint-700 hover:bg-mint-50 lg:size-10">
            {{ svg('heroicon-m-chevron-down', 'size-5') }}
        </button>
    </div>
    <template x-teleport="body">
        <div x-show="open" x-cloak x-ref="menu" x-trap="open" role="menu" aria-label="Format ekspor" x-on:click.outside="tutup()" x-on:scroll.window="tutup()"
            x-on:keydown.arrow-down.prevent="geser(1)" x-on:keydown.arrow-up.prevent="geser(-1)" x-bind:style="`top:${top}px;left:${left}px`"
            class="fixed z-50 w-56 rounded-md border border-neutral-200 bg-white py-1 shadow-md">
            @foreach ([['xlsx', 'Excel (.xlsx)', 'table-cells'], ['pdf', 'PDF', 'document-text']] as [$format, $teks, $ikon])
                <button type="button" role="menuitem" wire:click="{{ $aksi }}('{{ $format }}')" x-on:click="tutup()"
                    class="flex min-h-11 w-full items-center gap-2 px-3 text-left text-sm text-neutral-900 hover:bg-neutral-50 focus-visible:bg-neutral-50 lg:min-h-9">
                    {{ svg('heroicon-m-'.$ikon, 'size-5 text-mint-700') }}{{ $teks }}
                </button>
            @endforeach
            <p class="border-t border-neutral-200 px-3 pt-2 pb-1 text-xs text-neutral-500">Mengikuti filter aktif</p>
            @isset($tambahan)
                <div class="mt-1 border-t border-neutral-200 pt-1">{{ $tambahan }}</div>
            @endisset
        </div>
    </template>
</div>
