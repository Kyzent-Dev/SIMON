{{-- Notification list page (_shared.md §2): cards (not a table), bulk select + per-item "Tandai dibaca". Icon tile tones use only the verified status-badge pairs (DESIGN §3.4/§3.6).
     No animation on the list (DESIGN §7.5); `fokus-ke` moves focus to the h1 when a bulk action empties the view. --}}
@use('App\Support\JenisNotifikasi')
@php
    $nada = [
        'info' => 'bg-status-siap_dilaksanakan-bg text-status-siap_dilaksanakan-fg',
        'success' => 'bg-status-selesai-bg text-status-selesai-fg',
        'warning' => 'bg-status-menunggu_nomor-bg text-status-menunggu_nomor-fg',
        'danger' => 'bg-status-ditolak-bg text-status-ditolak-fg',
        'neutral' => 'bg-neutral-100 text-neutral-600',
    ];
    $ids = $notifikasi->pluck('id')->all();
    $semuaKosong = $notifikasi->isEmpty();
@endphp
<div x-on:fokus-ke.window="setTimeout(() => document.getElementById($event.detail.id)?.focus(), 50)">
    <x-ui.page-header title="Notifikasi" description="Semua pemberitahuan untuk akun Anda." title-id="judul-notifikasi">
        <x-slot:actions>
            @if ($belumDibaca > 0)
                <x-ui.button variant="secondary" type="button" icon="check" wire:click="tandaiSemua">Tandai semua dibaca</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs :tabs="['semua' => 'Semua', 'belum' => 'Belum dibaca'.($belumDibaca ? ' ('.$belumDibaca.')' : '')]" :active="$tab" model="tab" label="Filter notifikasi" panel="panel-notifikasi" />

    <div id="panel-notifikasi" role="tabpanel" aria-labelledby="panel-notifikasi-tab-{{ $tab }}" wire:loading.class="opacity-60" wire:target="tab,gotoPage,previousPage,nextPage,perPage">
        @if ($semuaKosong)
            <x-ui.card>
                <x-ui.empty-state icon="bell" :title="$tab === 'belum' ? 'Semua notifikasi sudah dibaca' : 'Belum ada notifikasi'"
                    :description="$tab === 'belum' ? 'Tidak ada pemberitahuan baru untuk Anda.' : 'Pemberitahuan tentang pengajuan dan jadwal akan muncul di sini.'">
                    @if ($tab === 'belum')
                        <x-ui.button variant="secondary" type="button" wire:click="$set('tab', 'semua')">Lihat semua</x-ui.button>
                    @endif
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-xs">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b border-neutral-200 bg-neutral-50 px-4 py-1"
                    x-data="{ ids: @js($ids) }">
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-3 text-sm font-medium text-neutral-900 lg:min-h-10">
                        <input type="checkbox" class="size-5 rounded-sm border-border-strong text-mint-700 focus:ring-2 focus:ring-mint-600 focus:ring-offset-2"
                            x-bind:checked="ids.length > 0 && ids.every((i) => $wire.terpilih.includes(i))"
                            x-on:change="$wire.terpilih = $event.target.checked ? ids : []">
                        Pilih semua di halaman ini
                    </label>
                    <x-ui.button variant="ghost" type="button" icon="check" wire:click="tandaiTerpilih" disabled-when="$wire.terpilih.length === 0">
                        Tandai dibaca<span class="tabular-nums" x-text="$wire.terpilih.length ? ' (' + $wire.terpilih.length + ')' : ''"></span>
                    </x-ui.button>
                </div>
                <ul role="list" class="divide-y divide-neutral-200" aria-label="Daftar notifikasi">
                    @foreach ($notifikasi as $n)
                        @php
                            $d = $n->data;
                            $ikon = JenisNotifikasi::ikon($d['jenis'] ?? '');
                            $baru = $n->read_at === null;
                            $waktu = $n->created_at->setTimezone('Asia/Jakarta');
                        @endphp
                        <li wire:key="n-{{ $n->id }}" @class(['flex items-start gap-3 p-4', 'bg-mint-50' => $baru])>
                            <label class="-m-3 inline-flex size-11 shrink-0 cursor-pointer items-center justify-center">
                                <input type="checkbox" value="{{ $n->id }}" wire:model="terpilih"
                                    class="size-5 rounded-sm border-border-strong text-mint-700 focus:ring-2 focus:ring-mint-600 focus:ring-offset-2">
                                <span class="sr-only">Pilih: {{ $d['judul'] ?? '' }}</span>
                            </label>
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $nada[$ikon['nada']] }}" aria-hidden="true">{{ svg('heroicon-o-'.$ikon['ikon'], 'size-6') }}</span>
                            <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                <a id="notif-{{ $n->id }}" href="{{ $d['url'] ?? '#' }}" x-on:click="if ($event.button !== 0 || $event.ctrlKey || $event.metaKey || $event.shiftKey) return; $event.preventDefault(); $wire.buka('{{ $n->id }}')" class="block min-w-0 rounded-sm text-sm">
                                    <span class="flex items-start gap-2">
                                        <span class="font-semibold text-neutral-900">{{ $d['judul'] ?? '' }}</span>
                                        @if ($baru)
                                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-mint-700" aria-hidden="true"></span><span class="sr-only">Belum dibaca</span>
                                        @endif
                                    </span>
                                    @if (! empty($d['konteks']))<span class="mt-0.5 block break-words tabular-nums text-neutral-600">{{ $d['konteks'] }}</span>@endif
                                    @if (! empty($d['catatan']))<span class="mt-0.5 line-clamp-2 block break-words text-neutral-600">Catatan: {{ $d['catatan'] }}</span>@endif
                                    <time datetime="{{ $waktu->toIso8601String() }}" title="{{ $waktu->translatedFormat('d F Y H.i') }} WIB" class="mt-1 block text-xs font-medium text-neutral-500">{{ $n->created_at->diffForHumans() }}</time>
                                </a>
                                @if ($baru)
                                    <x-ui.button variant="ghost" type="button" wire:click="tandaiDibaca('{{ $n->id }}')" class="shrink-0 self-start max-sm:-ml-3">
                                        Tandai dibaca<span class="sr-only">: {{ $d['judul'] ?? '' }}</span>
                                    </x-ui.button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <x-ui.pagination :paginator="$notifikasi" :per-page-options="[20, 50, 100]" class="mt-4" />
        @endif
    </div>
</div>
