{{-- admin.md "Objek Pemeriksaan": tab Kecamatan = tree (expandable rows, indent guides, aria-level, toggle aria-expanded), tabs Dinas/Badan, BUMD, RSUD = flat tables. Expanded state is client-side (Alpine). No animation on tables (DESIGN §7.5). --}}
<div>
    <x-ui.page-header title="Objek Pemeriksaan" description="Kecamatan beserta desa, sekolah, puskesmas, dan BUMDes di bawahnya, serta dinas/badan, BUMD, dan RSUD.">
        <x-slot:actions>
            <x-ui.button type="button" icon="plus" wire:click="create">Tambah objek</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs :tabs="$tabs" :active="$tab" model="tab" label="Jenis objek" panel="objek-panel" />

    <div role="tabpanel" id="objek-panel" aria-labelledby="objek-panel-tab-{{ $tab }}">
        <div class="mb-4 sm:max-w-sm">
            <x-ui.input label="Cari nama" name="search" type="search" wire:model.live.debounce.250ms="search" autocomplete="off" />
        </div>

        @if ($rows->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="building-library" :title="$searching ? 'Tidak ada objek yang cocok.' : 'Belum ada objek '.$tabs[$tab].'.'">
                    @unless ($searching)
                        <x-ui.button type="button" icon="plus" wire:click="create">Tambah objek</x-ui.button>
                    @endunless
                </x-ui.empty-state>
            </x-ui.card>
        @elseif ($tab === 'kecamatan')
            <div x-data="{ open: {}, all: @js($searching) }" class="rounded-lg border border-neutral-200 bg-white shadow-xs">
                <div role="region" aria-label="Pohon objek kecamatan" tabindex="0" class="overflow-x-auto rounded-lg">
                    <table role="treegrid" aria-label="Kecamatan dan objek di bawahnya" class="w-full border-collapse text-left text-sm text-neutral-900">
                        <thead>
                            <tr role="row">
                                <x-ui.th>Nama</x-ui.th>
                                <x-ui.th>Jenis</x-ui.th>
                                <x-ui.th><span class="sr-only">Aksi</span></x-ui.th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr role="row" aria-level="1" wire:key="kec-{{ $row->id }}" class="border-t border-neutral-200 hover:bg-neutral-50">
                                    <td class="h-12 min-w-56 px-4 py-1 font-medium">
                                        <div class="flex items-center gap-1">
                                            @if ($row->children->isNotEmpty())
                                                <button type="button" x-on:click="open[{{ $row->id }}] = ! open[{{ $row->id }}]" aria-controls="kec-anak-{{ $row->id }}"
                                                    x-bind:aria-expanded="(all || !! open[{{ $row->id }}]).toString()" aria-expanded="false"
                                                    aria-label="Tampilkan anak {{ $row->nama }}"
                                                    class="-ml-2 inline-flex size-11 shrink-0 items-center justify-center rounded-md text-neutral-700 hover:bg-neutral-100 lg:size-8">
                                                    <span x-show="! (all || open[{{ $row->id }}])">{{ svg('heroicon-m-chevron-right', 'size-5') }}</span>
                                                    <span x-show="all || open[{{ $row->id }}]" x-cloak>{{ svg('heroicon-m-chevron-down', 'size-5') }}</span>
                                                </button>
                                            @else
                                                <span class="-ml-2 inline-block size-11 shrink-0 lg:size-8" aria-hidden="true"></span>
                                            @endif
                                            <span>{{ $row->nama }}</span>
                                            @if ($row->children->isNotEmpty())
                                                <span class="ml-1 text-xs font-normal text-neutral-600">({{ $row->children->count() }})</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4">{{ $row->jenis->label() }}</td>
                                    <td class="whitespace-nowrap px-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <x-ui.button type="button" size="sm" variant="secondary" icon="plus" wire:click="addChild({{ $row->id }})" aria-label="Tambah anak untuk {{ $row->nama }}" class="max-lg:h-11">Tambah anak</x-ui.button>
                                            <x-ui.button type="button" size="sm" variant="secondary" wire:click="edit({{ $row->id }})" aria-label="Ubah {{ $row->nama }}" class="max-lg:h-11">Ubah</x-ui.button>
                                            <x-ui.dropdown label="Aksi untuk {{ $row->nama }}">
                                                <button type="button" role="menuitem" wire:click="confirmDelete({{ $row->id }})" class="!text-danger-700">{{ svg('heroicon-m-trash', 'size-5') }}Hapus</button>
                                            </x-ui.dropdown>
                                        </div>
                                    </td>
                                </tr>
                                @foreach ($row->children as $child)
                                    <tr role="row" aria-level="2" id="{{ $loop->first ? 'kec-anak-'.$row->id : '' }}" wire:key="anak-{{ $child->id }}" x-show="all || open[{{ $row->id }}]" x-cloak class="border-t border-neutral-200 bg-white hover:bg-neutral-50">
                                        <td class="h-12 px-4 py-1">
                                            <div class="ml-4 border-l-2 border-neutral-300 pl-4">{{ $child->nama }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-4">{{ $child->jenis->label() }}</td>
                                        <td class="whitespace-nowrap px-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <x-ui.button type="button" size="sm" variant="secondary" wire:click="edit({{ $child->id }})" aria-label="Ubah {{ $child->nama }}" class="max-lg:h-11">Ubah</x-ui.button>
                                                <x-ui.dropdown label="Aksi untuk {{ $child->nama }}">
                                                    <button type="button" role="menuitem" wire:click="confirmDelete({{ $child->id }})" class="!text-danger-700">{{ svg('heroicon-m-trash', 'size-5') }}Hapus</button>
                                                </x-ui.dropdown>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <x-ui.table :label="'Daftar '.$tabs[$tab]">
                <x-slot:head>
                    <x-ui.th>Nama</x-ui.th>
                    <x-ui.th>Jenis</x-ui.th>
                    <x-ui.th><span class="sr-only">Aksi</span></x-ui.th>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr wire:key="objek-{{ $row->id }}">
                        <td class="min-w-56 font-medium">{{ $row->nama }}</td>
                        <td class="whitespace-nowrap">{{ $row->jenis->label() }}</td>
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
    </div>

    <x-ui.drawer name="objek-form" :title="$editingId ? 'Ubah objek' : ($childOnly ? 'Tambah anak' : 'Tambah objek')" size="md" wire:model="open">
        <form novalidate id="objek-form" wire:submit="save" class="space-y-5">
            <x-ui.input label="Nama" name="nama" wire:model="nama" required autocomplete="off" :error="$errors->first('nama')" />
            <x-ui.select label="Jenis" name="jenis" wire:model.live="jenis" :options="$jenisOptions" :value="$jenis" required :error="$errors->first('jenis')" />
            @if ($needsParent)
                <x-ui.select label="Induk (kecamatan)" name="parentId" wire:model="parentId" :options="$parentOptions" :value="$parentId" placeholder="Pilih kecamatan" required :error="$errors->first('parentId')" />
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'objek-form' })">Batal</x-ui.button>
            <x-ui.button form="objek-form" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    <x-ui.modal name="objek-hapus" title="Hapus objek?" size="sm" wire:model="deleteOpen">
        <p><strong class="font-semibold">{{ $deleting?->nama }}</strong> akan dihapus permanen. Objek yang masih punya objek di bawahnya atau dipakai pada wilayah Irban tidak dapat dihapus.</p>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'objek-hapus' })">Batal</x-ui.button>
            <x-ui.button type="button" variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Hapus</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
