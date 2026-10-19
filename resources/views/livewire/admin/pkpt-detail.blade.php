{{-- admin.md "PKPT": item editor table with an inline add row; per-row inline edit; delete and "Sahkan PKPT" behind confirm modals.
     After Disahkan the page is read-only: no edit controls, "Jadwal berlaku" shows the effective range (KesesuaianPkpt) and, when revised,
     the original as "semula …" with a dashed marker echoing the public timeline (design/public/jadwal.md), plus a "Riwayat revisi" disclosure.
     No animation on the table or inline forms (DESIGN §7.5); modal transitions only. --}}
@use('App\Support\Tanggal')
@php
    $draf = $pkpt->isDraf();
    $tag = $pkpt->status->tag();
    $full = fn ($a, $b) => $a->translatedFormat('j F Y').' – '.$b->translatedFormat('j F Y');
    $cols = $draf ? 6 : 5;
@endphp
<div>
    <x-ui.page-header :title="'PKPT '.$pkpt->tahun">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-neutral-600">
                <x-app.tag :icon="$tag['icon']" :variant="$tag['variant']">{{ $pkpt->status->label() }}</x-app.tag>
                @if ($pkpt->nomor_sk && $pkpt->tanggal_sk)
                    <span>SK Bupati <span class="tabular-nums text-neutral-900">{{ $pkpt->nomor_sk }}</span>, {{ $pkpt->tanggal_sk->translatedFormat('d F Y') }}</span>
                @else
                    <span>Nomor dan tanggal SK Bupati belum diisi.</span>
                @endif
                @unless ($draf)
                    <span>Disahkan {{ $pkpt->disahkan_pada?->timezone('Asia/Jakarta')->translatedFormat('d F Y') }}</span>
                @endunless
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('admin.pkpt') }}" wire:navigate class="inline-flex h-11 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium text-mint-700 hover:bg-mint-50 lg:h-10">
                {{ svg('heroicon-m-arrow-left', 'size-5') }}Kembali ke daftar
            </a>
            @if ($draf)
                <a href="{{ route('admin.pkpt.impor', $pkpt) }}" wire:navigate class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-border-strong bg-white px-4 text-sm font-medium text-mint-700 hover:bg-mint-50 lg:h-10">
                    {{ svg('heroicon-m-arrow-up-tray', 'size-5') }}Impor Excel
                </a>
                <x-ui.button type="button" icon="check-badge" wire:click="$set('sahkanOpen', true)">Sahkan PKPT</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div class="w-full sm:w-64">
            <x-ui.select label="Irban" name="irbanId" wire:model.live="irbanId" :options="$irbans" :value="$irbanId" placeholder="Semua Irban" />
        </div>
        <p class="text-sm text-neutral-600" aria-live="polite">
            @if ($irbanId !== ''){{ $items->count() }} dari {{ $total }} item @else{{ $total }} item @endif
        </p>
    </div>

    @if ($draf)
        <form id="item-baru" wire:submit="tambah" novalidate></form>
        <form id="item-ubah" wire:submit="simpan" novalidate></form>
    @endif

    @if (! $draf && $items->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="calendar-days" title="Tidak ada item untuk filter ini." />
        </x-ui.card>
    @else
        <x-ui.table label="Item PKPT {{ $pkpt->tahun }}" wire:loading.class="opacity-60" wire:target="irbanId">
            <x-slot:head>
                <x-ui.th>Irban</x-ui.th>
                <x-ui.th>Jenis Penugasan</x-ui.th>
                <x-ui.th>{{ $draf ? 'Jadwal' : 'Jadwal berlaku' }}</x-ui.th>
                <x-ui.th>Keterangan</x-ui.th>
                <x-ui.th numeric>Target SPT</x-ui.th>
                @if ($draf)<x-ui.th><span class="sr-only">Aksi</span></x-ui.th>@endif
            </x-slot:head>

            @foreach ($items as $item)
                @if ($draf && $ubahId === $item->id)
                    <tr wire:key="item-{{ $item->id }}" data-selected>
                        @include('livewire.admin.partials.pkpt-item-fields', ['p' => 'ubah', 'form' => 'item-ubah', 'caption' => 'ubah item', 'tahun' => $pkpt->tahun])
                        <td class="whitespace-nowrap align-top !py-2 text-right">
                            <div class="flex flex-col items-stretch gap-1.5">
                                <x-ui.button type="button" size="sm" variant="ghost" wire:click="batal" class="max-lg:h-11">Batal</x-ui.button>
                                <x-ui.button form="item-ubah" size="sm" icon="check" wire:loading.attr="disabled" wire:target="simpan" class="max-lg:h-11">Simpan</x-ui.button>
                            </div>
                        </td>
                    </tr>
                @else
                    @php
                        [$mulai, $selesai] = $rentang[$item->id];
                        $label = $item->jenisPenugasan->nama.', '.$item->irban->nama.', '.$full($item->tanggal_mulai, $item->tanggal_selesai);
                    @endphp
                    <tr wire:key="item-{{ $item->id }}">
                        <td class="whitespace-nowrap align-top !py-3">{{ $item->irban->nama }}</td>
                        <td class="min-w-44 align-top !py-3"><x-app.type-chip :color="$item->jenisPenugasan->warna" :label="$item->jenisPenugasan->nama" /></td>
                        <td class="min-w-56 align-top !py-3">
                            <x-app.jadwal-berlaku :item="$item" :mulai="$mulai" :selesai="$selesai" />
                            @unless ($draf)<x-app.riwayat-revisi :revisi="$item->revisi" class="mt-2" />@endunless
                        </td>
                        <td class="min-w-32 align-top !py-3">
                            @if ($item->keterangan){{ $item->keterangan }}@else<span class="text-neutral-500">—</span>@endif
                        </td>
                        <td class="align-top !py-3 text-right">{{ $item->target_spt ?? '—' }}</td>
                        @if ($draf)
                            <td class="whitespace-nowrap align-top !py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-ui.button type="button" size="sm" variant="secondary" wire:click="edit({{ $item->id }})" aria-label="Ubah item {{ $label }}" class="max-lg:h-11">Ubah</x-ui.button>
                                    <x-ui.dropdown label="Aksi untuk item {{ $label }}">
                                        <button type="button" role="menuitem" wire:click="confirmDelete({{ $item->id }})" class="!text-danger-700">{{ svg('heroicon-m-trash', 'size-5') }}Hapus</button>
                                    </x-ui.dropdown>
                                </div>
                            </td>
                        @endif
                    </tr>
                @endif
            @endforeach

            @if ($draf)
                @if ($items->isEmpty())
                    <tr>
                        <td colspan="{{ $cols }}" class="!h-auto !py-6 text-center text-sm text-neutral-600">Belum ada item. Isi baris di bawah lalu pilih "Tambah", atau impor dari Excel.</td>
                    </tr>
                @endif
                <tr wire:key="item-baru" class="!bg-neutral-25">
                    @include('livewire.admin.partials.pkpt-item-fields', ['p' => 'baru', 'form' => 'item-baru', 'caption' => 'item baru', 'tahun' => $pkpt->tahun])
                    <td class="whitespace-nowrap align-top !py-2 text-right">
                        <x-ui.button form="item-baru" size="sm" icon="plus" wire:loading.attr="disabled" wire:target="tambah" class="max-lg:h-11">Tambah</x-ui.button>
                    </td>
                </tr>
            @endif
        </x-ui.table>
    @endif

    @if ($draf)
        <x-ui.modal name="item-hapus" title="Hapus item?" size="sm" wire:model="deleteOpen">
            @if ($deleting)
                <p>Item <strong class="font-semibold">{{ $deleting->jenisPenugasan->nama }}</strong> untuk {{ $deleting->irban->nama }} ({{ Tanggal::rentang($deleting->tanggal_mulai, $deleting->tanggal_selesai) }}) akan dihapus permanen.</p>
            @endif
            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'item-hapus' })">Batal</x-ui.button>
                <x-ui.button type="button" variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Hapus</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="pkpt-sahkan" :title="'Sahkan PKPT '.$pkpt->tahun.'?'" size="md" wire:model="sahkanOpen">
            <div class="space-y-3">
                <p>Setelah disahkan, PKPT ini menjadi hanya-baca: item tidak dapat lagi ditambah, diubah, dihapus, atau diimpor. Perubahan jadwal hanya lewat revisi jadwal yang disetujui pimpinan.</p>
                <p class="font-semibold">Pengesahan tidak dapat dibatalkan.</p>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-md border border-neutral-200 p-3">
                    <dt class="text-neutral-600">Jumlah item</dt><dd class="tabular-nums">{{ $total }}</dd>
                    <dt class="text-neutral-600">Nomor SK</dt><dd>{{ $pkpt->nomor_sk ?? 'Belum diisi' }}</dd>
                    <dt class="text-neutral-600">Tanggal SK</dt><dd>{{ $pkpt->tanggal_sk?->translatedFormat('d F Y') ?? 'Belum diisi' }}</dd>
                </dl>
                @if (! $pkpt->nomor_sk || ! $pkpt->tanggal_sk || $total === 0)
                    <x-ui.alert variant="warning">
                        {{ $total === 0 ? 'PKPT belum memiliki item.' : 'Isi nomor dan tanggal SK Bupati sebelum mengesahkan.' }}
                    </x-ui.alert>
                @endif
            </div>
            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'pkpt-sahkan' })">Batal</x-ui.button>
                <x-ui.button type="button" icon="check-badge" wire:click="sahkan" wire:loading.attr="disabled" wire:target="sahkan">Sahkan PKPT</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
