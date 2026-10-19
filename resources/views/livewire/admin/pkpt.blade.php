{{-- admin.md "PKPT": list per year with a rectangular Draf/Disahkan tag (x-app.tag, never a pill). Only Draf rows can be edited or deleted. Drawer for create/edit, confirm modal for delete. No animation on the table (DESIGN §7.5). --}}
<div>
    <x-ui.page-header title="PKPT" description="Program Kerja Pengawasan Tahunan per tahun. PKPT yang sudah disahkan tidak dapat diubah.">
        <x-slot:actions>
            <x-ui.button type="button" icon="plus" wire:click="create">Tambah PKPT</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="calendar-days" title="Belum ada PKPT. Tambahkan PKPT tahun ini.">
                <x-ui.button type="button" icon="plus" wire:click="create">Tambah PKPT</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table label="Daftar PKPT">
            <x-slot:head>
                <x-ui.th>Tahun</x-ui.th>
                <x-ui.th>Nomor SK</x-ui.th>
                <x-ui.th>Tanggal SK</x-ui.th>
                <x-ui.th>Status</x-ui.th>
                <x-ui.th numeric>Jumlah item</x-ui.th>
                <x-ui.th><span class="sr-only">Aksi</span></x-ui.th>
            </x-slot:head>
            @foreach ($rows as $row)
                @php $tag = $row->status->tag(); @endphp
                <tr wire:key="pkpt-{{ $row->id }}">
                    <td class="whitespace-nowrap">
                        <a href="{{ route('admin.pkpt.show', $row) }}" wire:navigate class="font-semibold text-mint-700 hover:underline">PKPT {{ $row->tahun }}</a>
                    </td>
                    <td class="min-w-56">
                        @if ($row->nomor_sk){{ $row->nomor_sk }}@else<span class="text-neutral-500">Belum diisi</span>@endif
                    </td>
                    <td class="whitespace-nowrap">
                        @if ($row->tanggal_sk){{ $row->tanggal_sk->translatedFormat('d F Y') }}@else<span class="text-neutral-500">Belum diisi</span>@endif
                    </td>
                    <td><x-app.tag :icon="$tag['icon']" :variant="$tag['variant']">{{ $row->status->label() }}</x-app.tag></td>
                    <td class="text-right">{{ $row->items_count }}</td>
                    <td class="whitespace-nowrap text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.pkpt.show', $row) }}" wire:navigate aria-label="{{ $row->isDraf() ? 'Kelola item' : 'Lihat item' }} PKPT {{ $row->tahun }}"
                                class="inline-flex h-8 items-center rounded-md border border-border-strong bg-white px-3 text-sm font-medium text-mint-700 hover:bg-mint-50 max-lg:h-11">
                                {{ $row->isDraf() ? 'Kelola item' : 'Lihat item' }}
                            </a>
                            @if ($row->isDraf())
                                <x-ui.dropdown label="Aksi untuk PKPT {{ $row->tahun }}">
                                    <button type="button" role="menuitem" wire:click="edit({{ $row->id }})" aria-label="Ubah PKPT {{ $row->tahun }}">{{ svg('heroicon-m-pencil-square', 'size-5') }}Ubah SK</button>
                                    <button type="button" role="menuitem" wire:click="confirmDelete({{ $row->id }})" class="!text-danger-700">{{ svg('heroicon-m-trash', 'size-5') }}Hapus</button>
                                </x-ui.dropdown>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    <x-ui.drawer name="pkpt-form" :title="$editingId ? 'Ubah PKPT' : 'Tambah PKPT'" size="md" wire:model="open">
        <form novalidate id="pkpt-form" wire:submit="save" class="space-y-5">
            <x-ui.input label="Tahun" name="tahun" type="number" inputmode="numeric" min="2000" max="{{ now()->year + 1 }}" wire:model="tahun" required class="tabular-nums" :error="$errors->first('tahun')" />
            <x-ui.input label="Nomor SK Bupati" name="nomor_sk" wire:model="nomor_sk" autocomplete="off" maxlength="255"
                helper="Wajib diisi sebelum PKPT disahkan." :error="$errors->first('nomor_sk')" />
            <x-ui.input label="Tanggal SK Bupati" name="tanggal_sk" type="date" wire:model="tanggal_sk" :error="$errors->first('tanggal_sk')" />
        </form>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'pkpt-form' })">Batal</x-ui.button>
            <x-ui.button form="pkpt-form" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    <x-ui.modal name="pkpt-hapus" title="Hapus PKPT?" size="sm" wire:model="deleteOpen">
        <p>PKPT <strong class="font-semibold">{{ $deleting?->tahun }}</strong> beserta {{ $deleting?->items_count ?? 0 }} itemnya akan dihapus permanen.</p>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'pkpt-hapus' })">Batal</x-ui.button>
            <x-ui.button type="button" variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Hapus</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
