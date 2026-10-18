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
            {{-- ARIA treegrid, row-focus mode: one row has tabindex 0 (roving); Up/Down/Home/End move between visible rows, Right expands (or enters the first child), Left collapses (or goes to the parent), Enter = Ubah. Buttons inside a row stay on Tab. --}}
            <div wire:key="tree-{{ $searching ? 'cari' : 'semua' }}" class="rounded-lg border border-neutral-200 bg-white shadow-xs"
                x-data="{
                    open: @js($searching ? $rows->filter(fn ($r) => $r->children->isNotEmpty())->mapWithKeys(fn ($r) => [$r->id => true])->all() : (object) []),
                    visible() { return [...this.$refs.body.querySelectorAll('tr[data-tree-row]')].filter((r) => r.offsetParent !== null) },
                    focusRow(r) { if (! r) return; this.$refs.body.querySelectorAll('tr[data-tree-row]').forEach((x) => x.tabIndex = -1); r.tabIndex = 0; r.focus() },
                    key(e) {
                        const r = e.target;
                        if (! r.matches('tr[data-tree-row]')) return;
                        const rows = this.visible(), i = rows.indexOf(r), id = r.dataset.id, parent = r.dataset.parent, isParent = r.dataset.hasChildren === '1';
                        let handled = true;
                        if (e.key === 'ArrowDown') this.focusRow(rows[i + 1]);
                        else if (e.key === 'ArrowUp') this.focusRow(rows[i - 1]);
                        else if (e.key === 'Home') this.focusRow(rows[0]);
                        else if (e.key === 'End') this.focusRow(rows[rows.length - 1]);
                        else if (e.key === 'ArrowRight' && isParent) { if (! this.open[id]) this.open[id] = true; else this.focusRow(rows[i + 1]) }
                        else if (e.key === 'ArrowLeft' && parent) this.focusRow(this.$refs.body.querySelector('tr[data-id=\'' + parent + '\']'));
                        else if (e.key === 'ArrowLeft' && isParent && this.open[id]) this.open[id] = false;
                        else if (e.key === 'Enter') r.querySelector('[data-edit]')?.click();
                        else handled = false;
                        if (handled) e.preventDefault();
                    },
                }"
                x-on:keydown="key($event)" x-on:focusin="if ($event.target.matches('tr[data-tree-row]')) focusRow($event.target)">
                <div role="region" aria-label="Pohon objek kecamatan" class="overflow-x-auto rounded-lg">
                    <table role="treegrid" aria-label="Kecamatan dan objek di bawahnya" class="w-full border-collapse text-left text-sm text-neutral-900">
                        <thead>
                            <tr role="row">
                                <x-ui.th>Nama</x-ui.th>
                                <x-ui.th>Jenis</x-ui.th>
                                <x-ui.th><span class="sr-only">Aksi</span></x-ui.th>
                            </tr>
                        </thead>
                        <tbody x-ref="body">
                            @foreach ($rows as $row)
                                @php $hasKids = $row->children->isNotEmpty(); @endphp
                                <tr role="row" data-tree-row data-id="{{ $row->id }}" data-has-children="{{ $hasKids ? 1 : 0 }}" aria-level="1" tabindex="{{ $loop->first ? 0 : -1 }}"
                                    @if ($hasKids) aria-expanded="false" x-bind:aria-expanded="(!! open[{{ $row->id }}]).toString()" @endif
                                    wire:key="kec-{{ $row->id }}" class="border-t border-neutral-200 hover:bg-neutral-50">
                                    <td role="gridcell" class="h-12 min-w-56 px-4 py-1 font-medium">
                                        <div class="flex items-center gap-1">
                                            @if ($hasKids)
                                                <button type="button" x-on:click="open[{{ $row->id }}] = ! open[{{ $row->id }}]"
                                                    aria-controls="{{ $row->children->map(fn ($c) => 'anak-'.$c->id)->implode(' ') }}"
                                                    x-bind:aria-expanded="(!! open[{{ $row->id }}]).toString()" aria-expanded="false"
                                                    aria-label="Tampilkan anak {{ $row->nama }}"
                                                    class="-ml-2 inline-flex size-11 shrink-0 items-center justify-center rounded-md text-neutral-700 hover:bg-neutral-100 lg:size-8">
                                                    <span x-show="! open[{{ $row->id }}]">{{ svg('heroicon-m-chevron-right', 'size-5') }}</span>
                                                    <span x-show="open[{{ $row->id }}]" x-cloak>{{ svg('heroicon-m-chevron-down', 'size-5') }}</span>
                                                </button>
                                            @else
                                                <span class="-ml-2 inline-block size-11 shrink-0 lg:size-8" aria-hidden="true"></span>
                                            @endif
                                            <span>{{ $row->nama }}</span>
                                            @if ($hasKids)
                                                <span class="ml-1 text-xs font-normal text-neutral-600">({{ $row->children->count() }})</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td role="gridcell" class="whitespace-nowrap px-4">{{ $row->jenis->label() }}</td>
                                    <td role="gridcell" class="whitespace-nowrap px-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <x-ui.button type="button" size="sm" variant="secondary" icon="plus" wire:click="addChild({{ $row->id }})" aria-label="Tambah anak untuk {{ $row->nama }}" class="max-lg:h-11">Tambah anak</x-ui.button>
                                            <x-ui.button type="button" size="sm" variant="secondary" data-edit wire:click="edit({{ $row->id }})" aria-label="Ubah {{ $row->nama }}" class="max-lg:h-11">Ubah</x-ui.button>
                                            <x-ui.dropdown label="Aksi untuk {{ $row->nama }}">
                                                <button type="button" role="menuitem" wire:click="confirmDelete({{ $row->id }})" class="!text-danger-700">{{ svg('heroicon-m-trash', 'size-5') }}Hapus</button>
                                            </x-ui.dropdown>
                                        </div>
                                    </td>
                                </tr>
                                @foreach ($row->children as $child)
                                    <tr role="row" data-tree-row data-id="{{ $child->id }}" data-parent="{{ $row->id }}" aria-level="2" tabindex="-1" id="anak-{{ $child->id }}"
                                        wire:key="anak-{{ $child->id }}" x-show="open[{{ $row->id }}]" x-cloak class="border-t border-neutral-200 bg-white hover:bg-neutral-50">
                                        <td role="gridcell" class="h-12 px-4 py-1">
                                            <div class="ml-4 border-l-2 border-neutral-300 pl-4">{{ $child->nama }}</div>
                                        </td>
                                        <td role="gridcell" class="whitespace-nowrap px-4">{{ $child->jenis->label() }}</td>
                                        <td role="gridcell" class="whitespace-nowrap px-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <x-ui.button type="button" size="sm" variant="secondary" data-edit wire:click="edit({{ $child->id }})" aria-label="Ubah {{ $child->nama }}" class="max-lg:h-11">Ubah</x-ui.button>
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
