{{-- Header bell (_shared.md §2, DESIGN §7.2 bell-ring): 400px popover right-aligned to the header (≥640), bottom sheet with scrim and focus trap below 640.
     wire:poll passes the open state so an open list is never re-rendered under the reader (count only refreshes once closed).
     A count increase rings the bell once and pops the badge (motion-safe only: nothing moves under prefers-reduced-motion) and is announced in the status region. --}}
<div class="shrink-0"
    x-data="{
        open: false, sheet: false, dering: false,
        buka() { this.sheet = matchMedia('(max-width: 639.98px)').matches; this.open = true; $wire.$set('terbuka', true); this.$nextTick(() => this.$refs.judul.focus()) },
        tutup(paksa = true) { if (! this.open) return; this.open = false; $wire.terbuka = false; this.$nextTick(() => { if (paksa || document.activeElement === document.body) this.$refs.bell.focus() }) },
    }"
    x-on:keydown.escape.window="tutup()"
    x-on:lonceng-dering.window="dering = false; $nextTick(() => dering = true)"
    x-on:focusout="if (open && ! sheet && $event.relatedTarget && ! $el.contains($event.relatedTarget)) tutup(false)"
    wire:poll.60s="periksa(open)">
    <span role="status" aria-live="polite" aria-atomic="true" class="sr-only">{{ $baru ? $baru.' notifikasi baru' : '' }}</span>

    <div x-on:click.outside="if (! sheet) tutup(false)">
        <button type="button" x-ref="bell" x-on:click="open ? tutup() : buka()" title="Notifikasi"
            aria-label="{{ $belum ? "Notifikasi, {$belum} belum dibaca" : 'Notifikasi, tidak ada yang belum dibaca' }}"
            aria-haspopup="dialog" aria-controls="lonceng-dialog" aria-expanded="false" x-bind:aria-expanded="open.toString()"
            class="relative inline-flex size-11 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 lg:size-10">
            <span class="inline-flex origin-top" x-bind:class="dering && 'motion-safe:animate-bell'" x-on:animationend.self="dering = false">{{ svg('heroicon-o-bell', 'size-6') }}</span>
            @if ($belum)
                <span aria-hidden="true" x-bind:class="dering && 'motion-safe:animate-badge-pop'"
                    class="absolute top-0.5 right-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-danger-700 px-1 text-[11px] leading-none font-bold tabular-nums text-white ring-2 ring-white" data-lencana>{{ $belum > 9 ? '9+' : $belum }}</span>
            @endif
        </button>

        <div x-show="open && sheet" x-cloak x-on:click="tutup()" aria-hidden="true" class="fixed inset-0 z-50 bg-scrim sm:hidden"></div>

        <div x-show="open" x-cloak id="lonceng-dialog" role="dialog" aria-label="Notifikasi" x-bind:aria-modal="sheet ? 'true' : null"
            x-trap.noscroll="open && sheet"
            x-transition:enter="transition-opacity duration-[var(--dur-fast)] ease-[var(--ease-enter)]" x-transition:enter-start="opacity-0"
            class="fixed inset-x-0 bottom-0 z-50 flex max-h-[80vh] flex-col rounded-t-lg border border-neutral-200 bg-white shadow-lg sm:absolute sm:inset-x-auto sm:top-full sm:right-4 sm:bottom-auto sm:mt-1 sm:max-h-[min(36rem,calc(100dvh-5rem))] sm:w-[400px] sm:rounded-lg sm:shadow-md md:right-6 lg:right-8">
            <div class="flex shrink-0 items-center justify-between gap-2 border-b border-neutral-200 py-2 pr-2 pl-4">
                <h2 x-ref="judul" tabindex="-1" class="font-display text-base font-bold text-neutral-900">Notifikasi</h2>
                <div class="flex items-center gap-1">
                    @if ($belum)
                        <x-ui.button variant="ghost" type="button" class="px-3" x-on:click="const judul = $refs.judul; $wire.tandaiSemuaDibaca().then(() => judul.focus())">Tandai semua dibaca</x-ui.button>
                    @endif
                    <button type="button" x-on:click="tutup()" aria-label="Tutup notifikasi" title="Tutup"
                        class="inline-flex size-11 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 sm:hidden">
                        {{ svg('heroicon-o-x-mark', 'size-6') }}
                    </button>
                </div>
            </div>

            <div class="shrink-0 px-2 pt-1 [&>[role=tablist]]:mb-0">
                <x-ui.tabs :tabs="['semua' => 'Semua', 'belum' => 'Belum dibaca']" :active="$tab" model="tab" label="Filter notifikasi" panel="panel-lonceng" />
            </div>

            <div id="panel-lonceng" role="tabpanel" aria-labelledby="panel-lonceng-tab-{{ $tab }}" class="min-h-0 flex-1 overflow-y-auto" wire:loading.class="opacity-60" wire:target="tab">
                @if (! $terbuka)
                @elseif ($notifikasi->isEmpty())
                    <p class="flex flex-col items-center gap-2 px-4 py-10 text-center text-sm font-medium text-neutral-600">
                        {{ svg('heroicon-o-bell', 'size-8 text-mint-600', ['aria-hidden' => 'true']) }}
                        {{ $tab === 'belum' ? 'Semua notifikasi sudah dibaca' : 'Belum ada notifikasi' }}
                    </p>
                @else
                    <ul role="list" class="divide-y divide-neutral-200" aria-label="Notifikasi terbaru">
                        @foreach ($notifikasi as $n)
                            <li wire:key="l-{{ $n->id }}">
                                <a href="{{ $n->data['url'] ?? '#' }}" x-on:click="if ($event.button !== 0 || $event.ctrlKey || $event.metaKey || $event.shiftKey) return; $event.preventDefault(); $wire.buka('{{ $n->id }}')"
                                    @class(['flex items-start gap-3 px-4 py-3 text-sm focus-visible:-outline-offset-2', 'bg-mint-50' => $n->read_at === null])>
                                    <x-app.notifikasi-ikon :jenis="$n->data['jenis'] ?? ''" />
                                    <span class="block min-w-0 flex-1">
                                        <x-app.notifikasi-teks :n="$n" :catatan="false" />
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="shrink-0 border-t border-neutral-200 p-2">
                <a href="{{ route('notifikasi.index') }}" wire:navigate class="flex min-h-11 items-center justify-center rounded-md text-sm font-medium text-mint-700 hover:bg-mint-50 lg:min-h-10">Lihat semua notifikasi</a>
            </div>
        </div>
    </div>
</div>
