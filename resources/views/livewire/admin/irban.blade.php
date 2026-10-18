{{-- admin.md Master Data "Irban": table + create/edit drawer (lg 720), tembusan as an add/remove list editor, confirm modal for delete. No animation on the table (DESIGN §7.5). --}}
<div>
    <x-ui.page-header title="Irban" description="Daftar Inspektur Pembantu beserta kode nomor SPT dan email notifikasi.">
        <x-slot:actions>
            <x-ui.button type="button" icon="plus" wire:click="create">Tambah Irban</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="building-office-2" title="Belum ada data Irban.">
                <x-ui.button type="button" icon="plus" wire:click="create">Tambah Irban</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table label="Daftar Irban">
            <x-slot:head>
                <x-ui.th>Nama</x-ui.th>
                <x-ui.th>Kode nomor</x-ui.th>
                <x-ui.th>Email notifikasi</x-ui.th>
                <x-ui.th>Email tembusan</x-ui.th>
                <x-ui.th><span class="sr-only">Aksi</span></x-ui.th>
            </x-slot:head>
            @foreach ($rows as $row)
                <tr wire:key="irban-{{ $row->id }}">
                    <td class="min-w-28 font-medium">{{ $row->nama }}</td>
                    <td class="whitespace-nowrap">{{ $row->kode_nomor }}</td>
                    <td class="min-w-40">{{ $row->email_notifikasi ?: '—' }}</td>
                    <td class="min-w-40">{{ $row->email_tembusan ? implode(', ', $row->email_tembusan) : '—' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <div class="flex items-center justify-end gap-2">
                            <x-ui.button type="button" size="sm" variant="secondary" wire:click="edit({{ $row->id }})" aria-label="Ubah {{ $row->nama }}" class="max-lg:h-11">Ubah</x-ui.button>
                            <x-ui.dropdown label="Aksi untuk {{ $row->nama }}">
                                <button type="button" role="menuitem" wire:click="confirmDelete({{ $row->id }})" class="!text-danger-700">{{ svg('heroicon-m-trash', 'size-5') }}Hapus</button>
                            </x-ui.dropdown>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    <x-ui.drawer name="irban-form" :title="$editingId ? 'Ubah Irban' : 'Tambah Irban'" size="lg" wire:model="open">
        <form novalidate id="irban-form" wire:submit="save" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Nama" name="nama" wire:model="nama" required autocomplete="off" :error="$errors->first('nama')" />
                <x-ui.input label="Kode nomor" name="kodeNomor" id="kodeNomor" wire:model="kodeNomor" required autocomplete="off" helper="Dipakai pada nomor SPT, mis. INSPEKTORAT-I." :error="$errors->first('kodeNomor')" />
            </div>
            <x-ui.input label="Email notifikasi" name="emailNotifikasi" id="emailNotifikasi" type="email" wire:model="emailNotifikasi" autocomplete="off" :error="$errors->first('emailNotifikasi')" />

            <fieldset class="space-y-3">
                <legend class="mb-1.5 text-sm font-medium text-neutral-900">Email tembusan</legend>
                @forelse ($tembusan as $i => $email)
                    <div wire:key="tembusan-{{ $i }}" class="flex items-start gap-2">
                        <div class="flex-1">
                            <x-ui.input :label="'Email tembusan '.($i + 1)" :name="'tembusan-'.$i" :id="'tembusan-'.$i" type="email" wire:model="tembusan.{{ $i }}" autocomplete="off" :error="$errors->first('tembusan.'.$i)" />
                        </div>
                        <x-ui.button type="button" variant="secondary" wire:click="removeTembusan({{ $i }})" aria-label="Hapus email tembusan {{ $i + 1 }}" class="mt-7 shrink-0">
                            {{ svg('heroicon-m-trash', 'size-5') }}<span class="sr-only">Hapus</span>
                        </x-ui.button>
                    </div>
                @empty
                    <p class="text-sm text-neutral-600">Belum ada email tembusan.</p>
                @endforelse
                <x-ui.button type="button" variant="secondary" icon="plus" wire:click="addTembusan">Tambah email tembusan</x-ui.button>
            </fieldset>
        </form>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'irban-form' })">Batal</x-ui.button>
            <x-ui.button form="irban-form" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    <x-ui.modal name="irban-hapus" title="Hapus Irban?" size="sm" wire:model="deleteOpen">
        <p><strong class="font-semibold">{{ $deleting?->nama }}</strong> akan dihapus permanen. Irban yang sudah dipakai pengguna atau komposisi tahunan tidak dapat dihapus.</p>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'irban-hapus' })">Batal</x-ui.button>
            <x-ui.button type="button" variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Hapus</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
