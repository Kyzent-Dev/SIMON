{{-- Log Aktivitas (design/dashboard/admin.md "Log Aktivitas", _shared.md §6 filter bar). Compact read-only table, newest first, disclosure per row for the diff.
     Filters live in the URL; below 768 they move into a drawer. No edit/delete controls and no animation (DESIGN §7.5). --}}
@php
    use App\Support\LabelAktivitas;
    $jumlah = count($aktif);
    $chip = [
        'dari' => fn ($v) => 'Dari '.\Carbon\CarbonImmutable::parse($v)->translatedFormat('d F Y'),
        'sampai' => fn ($v) => 'Sampai '.\Carbon\CarbonImmutable::parse($v)->translatedFormat('d F Y'),
        'pengguna' => fn ($v) => 'Pengguna: '.($penggunaOpsi[$v] ?? $v),
        'objek' => fn ($v) => 'Objek: '.LabelAktivitas::opsiObjek()[$v],
        'aksi' => fn ($v) => 'Aksi: '.\App\Livewire\LogAktivitas::AKSI[$v],
        'spt' => fn ($v) => 'SPT #'.$v,
    ];
@endphp
<div>
    <x-ui.page-header title="Log Aktivitas" description="Catatan perubahan data. Hanya dapat dibaca, tidak dapat diubah atau dihapus." />

    <div class="mb-4 space-y-3">
        <button type="button" x-data x-on:click="$dispatch('open-modal', { name: 'filter-log' })"
            class="inline-flex h-11 items-center gap-2 rounded-md border border-border-strong bg-white px-4 text-sm font-medium text-mint-700 hover:bg-mint-50 md:hidden">
            {{ svg('heroicon-m-funnel', 'size-5') }}Filter{{ $jumlah ? " ({$jumlah})" : '' }}
        </button>
        <div class="hidden md:flex md:flex-wrap md:items-end md:gap-3">
            @include('livewire.partials.log-filter', ['p' => 'f'])
            @if ($jumlah)
                <x-ui.button type="button" variant="ghost" wire:click="aturUlang">Atur ulang</x-ui.button>
            @endif
        </div>
        @if ($jumlah)
            <ul role="list" aria-label="Filter aktif" class="flex flex-wrap gap-2">
                @foreach ($aktif as $k => $v)
                    <li class="inline-flex h-11 items-center rounded-md border border-mint-200 bg-mint-50 pl-3 text-sm font-medium text-neutral-900 lg:h-8">
                        {{ $chip[$k]($v) }}
                        <button type="button" wire:click="$set('{{ $k }}', '')" aria-label="Hapus filter {{ $chip[$k]($v) }}"
                            class="inline-flex size-11 items-center justify-center rounded-md text-neutral-600 hover:text-neutral-900 lg:size-8">{{ svg('heroicon-m-x-mark', 'size-4') }}</button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-ui.drawer name="filter-log" title="Filter" size="sm">
        <div class="space-y-4">
            @include('livewire.partials.log-filter', ['p' => 'd'])
        </div>
        <x-slot:footer>
            @if ($jumlah)
                <x-ui.button type="button" variant="ghost" wire:click="aturUlang">Atur ulang</x-ui.button>
            @endif
            <x-ui.button type="button" x-on:click="$dispatch('close-modal', { name: 'filter-log' })">Tutup</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    <div wire:loading.delay.class="opacity-60" wire:loading.delay.attr="aria-busy" wire:target="dari,sampai,pengguna,objek,aksi,spt,aturUlang,gotoPage,previousPage,nextPage">
        @if ($baris->total() === 0)
            <x-ui.card>
                <x-ui.empty-state icon="clipboard-document-list" :title="$jumlah ? 'Tidak ada aktivitas yang cocok dengan filter.' : 'Belum ada aktivitas.'">
                    @if ($jumlah)
                        <x-ui.button type="button" variant="secondary" wire:click="aturUlang">Atur ulang filter</x-ui.button>
                    @endif
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <x-ui.table grouped density="compact" label="Log aktivitas">
                <x-slot:head>
                    <x-ui.th>Waktu</x-ui.th>
                    <x-ui.th>Pengguna</x-ui.th>
                    <x-ui.th>Diinput oleh</x-ui.th>
                    <x-ui.th>Aksi</x-ui.th>
                    <x-ui.th>Objek</x-ui.th>
                    <x-ui.th>IP</x-ui.th>
                </x-slot:head>
                @foreach ($baris as $a)
                    <x-app.log-aktivitas-baris :a="$a" />
                @endforeach
            </x-ui.table>
            <x-ui.pagination :paginator="$baris" :perPageOptions="[]" class="mt-4" />
        @endif
    </div>
</div>
