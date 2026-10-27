{{-- admin.md "Pegawai": table + create/edit drawer (lg 720), NIP 18-digit mask with counter, confirm modal for delete. No animation on the table (DESIGN §7.5). --}}
@php
    $dir = fn ($col) => $sortBy === $col ? $sortDir : null;
    $filtered = trim($search) !== '' || $filter !== 'semua';
@endphp
<div>
    <x-ui.page-header title="Pegawai" description="Data pegawai Inspektorat yang dapat ditugaskan ke tim dan Irban.">
        <x-slot:actions>
            <a href="{{ route('admin.pegawai.impor') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-border-strong bg-white px-4 text-sm font-medium text-mint-700 hover:bg-mint-50 lg:h-10">
                {{ svg('heroicon-m-arrow-up-tray', 'size-5') }}Impor dari Excel
            </a>
            <x-ui.button type="button" icon="plus" wire:click="create">Tambah pegawai</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($total === 0)
        <x-ui.card>
            <x-ui.empty-state icon="users" title="Belum ada data pegawai. Tambah manual atau impor dari Excel.">
                <x-ui.button type="button" icon="plus" wire:click="create">Tambah pegawai</x-ui.button>
                <a href="{{ route('admin.pegawai.impor') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-border-strong bg-white px-4 text-sm font-medium text-mint-700 hover:bg-mint-50 lg:h-10">Impor dari Excel</a>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_14rem]">
            <div>
                <label for="cari" class="mb-1.5 block text-sm font-medium text-neutral-900">Cari</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-neutral-500">{{ svg('heroicon-m-magnifying-glass', 'size-5') }}</span>
                    <input id="cari" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari…" autocomplete="off"
                        class="block h-12 w-full rounded-md border-border-strong bg-white pr-3 pl-10 text-base text-neutral-900 focus:border-mint-700 focus:ring-2 focus:ring-mint-600 lg:h-10 lg:text-sm">
                </div>
                <p class="mt-1.5 text-xs text-neutral-500">Nama, NIP, atau email.</p>
            </div>
            <x-ui.select label="Status" name="filter" wire:model.live="filter" :options="['semua' => 'Semua', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']" />
        </div>

        <x-ui.table label="Daftar pegawai">
            <x-slot:head>
                <x-ui.th sortable :direction="$dir('nama')" wire:click="sort('nama')">Nama bergelar</x-ui.th>
                <x-ui.th sortable :direction="$dir('nip')" wire:click="sort('nip')">NIP</x-ui.th>
                <x-ui.th>Pangkat/Golongan</x-ui.th>
                <x-ui.th>Jabatan</x-ui.th>
                <x-ui.th>Email</x-ui.th>
                <x-ui.th>Status</x-ui.th>
                <x-ui.th><span class="sr-only">Aksi</span></x-ui.th>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr wire:key="pegawai-{{ $row->id }}">
                    <td class="min-w-40 font-medium">{{ $row->nama_bergelar }}</td>
                    <td class="whitespace-nowrap">{{ $row->nip }}</td>
                    <td class="min-w-32">{{ collect([$row->pangkat, $row->golongan])->filter()->implode(' / ') ?: '—' }}</td>
                    <td class="min-w-32">{{ $row->jabatan ?: '—' }}</td>
                    <td class="min-w-40 break-all">{{ $row->email ?: '—' }}</td>
                    <td>
                        @if ($row->aktif)
                            <span class="inline-flex items-center gap-1.5 text-success-700">{{ svg('heroicon-m-check-circle', 'size-5') }}Aktif</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-neutral-600">{{ svg('heroicon-m-minus-circle', 'size-5') }}Nonaktif</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <div class="flex items-center justify-end gap-2">
                            <x-ui.button type="button" size="sm" variant="secondary" wire:click="edit({{ $row->id }})" aria-label="Ubah {{ $row->nama_bergelar }}" class="max-lg:h-11">Ubah</x-ui.button>
                            <x-ui.dropdown label="Aksi untuk {{ $row->nama_bergelar }}">
                                <button type="button" role="menuitem" wire:click="toggleAktif({{ $row->id }})">{{ svg('heroicon-m-power', 'size-5 text-neutral-600') }}{{ $row->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                <button type="button" role="menuitem" wire:click="confirmDelete({{ $row->id }})" class="!text-danger-700">{{ svg('heroicon-m-trash', 'size-5') }}Hapus</button>
                            </x-ui.dropdown>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="!h-auto !p-0">
                        <x-ui.empty-state icon="magnifying-glass" title="Tidak ada pegawai yang cocok." description="Ubah kata kunci atau filter status." />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        <x-ui.pagination :paginator="$rows" class="mt-4" />
    @endif

    <x-ui.drawer name="pegawai-form" :title="$editingId ? 'Ubah pegawai' : 'Tambah pegawai'" size="lg" wire:model="open">
        <form novalidate id="pegawai-form" wire:submit="save" class="space-y-5">
            <x-ui.input label="Nama bergelar" name="nama" wire:model="nama" required autocomplete="off" :error="$errors->first('nama')" />
            <div>
                <x-ui.input label="NIP" name="nip" wire:model="nip" required inputmode="numeric" maxlength="18" autocomplete="off"
                    aria-describedby="nip-counter" class="tabular-nums"
                    x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, 18); $wire.nip = $el.value"
                    :error="$errors->first('nip')" />
                <p id="nip-counter" class="mt-1.5 text-xs text-neutral-600 tabular-nums"><span x-text="($wire.nip ?? '').length">0</span>/18 digit</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Pangkat" name="pangkat" wire:model="pangkat" autocomplete="off" :error="$errors->first('pangkat')" />
                <x-ui.input label="Golongan" name="golongan" wire:model="golongan" autocomplete="off" :error="$errors->first('golongan')" />
            </div>
            <x-ui.input label="Jabatan" name="jabatan" wire:model="jabatan" autocomplete="off" :error="$errors->first('jabatan')" />
            <x-ui.input label="Email" name="email" type="email" wire:model="email" autocomplete="off" helper="Opsional. Untuk salinan email notifikasi SPT yang diinput pegawai ini." :error="$errors->first('email')" />
            <x-ui.switch label="Aktif" name="aktif" wire:model="aktif" description="Pegawai nonaktif tidak muncul sebagai pilihan baru." />
        </form>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'pegawai-form' })">Batal</x-ui.button>
            <x-ui.button form="pegawai-form" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    <x-ui.modal name="pegawai-hapus" title="Hapus pegawai?" size="sm" wire:model="deleteOpen">
        <p>Data <strong class="font-semibold">{{ $deleting?->nama_bergelar }}</strong> akan dihapus permanen. Jika hanya tidak bertugas lagi, pilih Nonaktifkan.</p>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'pegawai-hapus' })">Batal</x-ui.button>
            <x-ui.button type="button" variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Hapus</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
