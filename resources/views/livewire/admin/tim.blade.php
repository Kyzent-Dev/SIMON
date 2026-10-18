{{-- admin.md "Tim": irban + tahun filter, one card per team, create/edit drawer with the member editor and a live validation strip (icon + text + count, aria-live). Reordering uses Naikkan/Turunkan buttons, no drag. No animation on lists (DESIGN §7.5). --}}
<div>
    <x-ui.page-header title="Tim" description="Susun tim pemeriksa setiap Irban per tahun. Setiap tim wajib punya tepat satu Supervisor/Dalnis dan tepat satu Ketua.">
        <x-slot:actions>
            @if ($irbans)
                <x-ui.button type="button" icon="plus" wire:click="create">Tambah tim</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $irbans)
        <x-ui.card>
            <x-ui.empty-state icon="building-office-2" title="Belum ada data Irban." description="Tambahkan Irban terlebih dahulu.">
                <a href="{{ route('admin.irban') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-border-strong bg-white px-4 text-sm font-medium text-mint-700 hover:bg-mint-50 lg:h-10">Buka halaman Irban</a>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="mb-4 grid gap-4 sm:max-w-xl sm:grid-cols-2">
            <x-ui.select label="Irban" name="irbanId" wire:model.live="irbanId" :options="$irbans" :value="$irbanId" />
            <x-ui.select label="Tahun" name="tahun" wire:model.live="tahun" :options="$years" :value="$tahun" />
        </div>

        @if ($teams->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="user-group" title="Belum ada tim untuk Irban dan tahun ini.">
                    <x-ui.button type="button" icon="plus" wire:click="create">Tambah tim</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($teams as $team)
                    <x-ui.card wire:key="tim-{{ $team->id }}">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h2 class="font-display text-lg font-bold text-neutral-900">{{ $team->nama }}</h2>
                            <div class="flex items-center gap-2">
                                <x-ui.button type="button" size="sm" variant="secondary" wire:click="edit({{ $team->id }})" aria-label="Ubah {{ $team->nama }}" class="max-lg:h-11">Ubah</x-ui.button>
                                <x-ui.dropdown label="Aksi untuk {{ $team->nama }}">
                                    <button type="button" role="menuitem" wire:click="confirmDelete({{ $team->id }})" class="!text-danger-700">{{ svg('heroicon-m-trash', 'size-5') }}Hapus</button>
                                </x-ui.dropdown>
                            </div>
                        </div>
                        <ol class="divide-y divide-neutral-200 text-sm">
                            @foreach ($team->anggota as $m)
                                <li class="flex items-baseline gap-3 py-2">
                                    <span class="w-6 shrink-0 text-neutral-600 tabular-nums">{{ $m->urutan }}.</span>
                                    <span class="min-w-0 flex-1 text-neutral-900">{{ $m->pegawai->nama_bergelar }}</span>
                                    <span class="shrink-0 font-medium text-neutral-900">{{ $m->peran_default->label() }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    @endif

    <x-ui.drawer name="tim-form" :title="$editingId ? 'Ubah tim' : 'Tambah tim'" size="lg" wire:model="open">
        <form novalidate id="tim-form" wire:submit="save" class="space-y-5" x-data x-on:tim-focus.window="$nextTick(() => document.getElementById($event.detail.id)?.focus())">
            <x-ui.input label="Nama tim" name="nama" wire:model="nama" required autocomplete="off" :error="$errors->first('nama') ?: $errors->first('tahun')" />

            <fieldset class="space-y-3">
                <legend class="mb-1.5 text-sm font-medium text-neutral-900">Anggota tim</legend>

                <div class="grid gap-3 rounded-md border border-neutral-200 p-3 sm:grid-cols-[1fr_12rem_auto] sm:items-end">
                    <x-ui.combobox name="newPegawai" label="Pegawai" :options="$options" wire:model="newPegawai" placeholder="Cari pegawai…" />
                    <x-ui.select label="Peran" name="newPeran" wire:model="newPeran" :options="$perans" :value="$newPeran" />
                    <x-ui.button type="button" variant="secondary" icon="plus" wire:click="addMember">Tambahkan</x-ui.button>
                </div>

                <div aria-live="polite" class="space-y-3">
                    <ul class="space-y-1.5 rounded-md border border-neutral-200 p-3 text-sm text-neutral-900" aria-label="Syarat komposisi tim">
                        @foreach ([['Tepat satu Supervisor/Dalnis', $supervisorCount], ['Tepat satu Ketua', $ketuaCount]] as [$rule, $n])
                            <li class="flex items-center gap-2">
                                @if ($n === 1)
                                    {{ svg('heroicon-m-check-circle', 'size-5 shrink-0 text-success-700') }}
                                @else
                                    {{ svg('heroicon-m-x-circle', 'size-5 shrink-0 text-danger-700') }}
                                @endif
                                <span>{{ $rule }}: {{ $n }} dipilih, {{ $n === 1 ? 'terpenuhi' : 'belum terpenuhi' }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($errors->has('members'))
                        <x-ui.alert variant="danger">
                            @foreach ($errors->get('members') as $message)
                                <p>{{ $message }}</p>
                            @endforeach
                        </x-ui.alert>
                    @endif
                </div>

                @forelse ($members as $i => $m)
                    @php $nm = $options[$m['pegawai_id']] ?? 'pegawai'; @endphp
                    <div wire:key="member-{{ $m['pegawai_id'] }}" class="flex flex-wrap items-end gap-2 rounded-md border border-neutral-200 p-3">
                        <p class="min-w-0 basis-full text-sm font-medium text-neutral-900"><span class="tabular-nums text-neutral-600">{{ $i + 1 }}.</span> {{ $nm }}</p>
                        <div class="min-w-40 flex-1">
                            <x-ui.select :label="'Peran '.$nm" :name="'peran-'.$i" :id="'peran-'.$i" wire:model.live="members.{{ $i }}.peran_default" :options="$perans" :value="$m['peran_default']" />
                        </div>
                        <x-ui.button type="button" variant="secondary" id="naikkan-{{ $m['pegawai_id'] }}" wire:click="moveUp({{ $i }})" aria-label="Naikkan {{ $nm }}" :disabled="$i === 0" class="w-11 !px-0 lg:w-auto lg:!px-3">
                            {{ svg('heroicon-m-arrow-up', 'size-5') }}<span class="max-lg:sr-only">Naikkan</span>
                        </x-ui.button>
                        <x-ui.button type="button" variant="secondary" id="turunkan-{{ $m['pegawai_id'] }}" wire:click="moveDown({{ $i }})" aria-label="Turunkan {{ $nm }}" :disabled="$i === count($members) - 1" class="w-11 !px-0 lg:w-auto lg:!px-3">
                            {{ svg('heroicon-m-arrow-down', 'size-5') }}<span class="max-lg:sr-only">Turunkan</span>
                        </x-ui.button>
                        <x-ui.button type="button" variant="secondary" wire:click="removeMember({{ $i }})" aria-label="Hapus {{ $nm }} dari tim" class="w-11 !px-0">
                            {{ svg('heroicon-m-trash', 'size-5') }}
                        </x-ui.button>
                    </div>
                @empty
                    <p class="text-sm text-neutral-600">Belum ada anggota. Tambahkan pegawai di atas.</p>
                @endforelse
            </fieldset>

        </form>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'tim-form' })">Batal</x-ui.button>
            <x-ui.button form="tim-form" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    <x-ui.modal name="tim-hapus" title="Hapus tim?" size="sm" wire:model="deleteOpen">
        <p><strong class="font-semibold">{{ $deleting?->nama }}</strong> beserta daftar anggotanya akan dihapus permanen.</p>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'tim-hapus' })">Batal</x-ui.button>
            <x-ui.button type="button" variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Hapus</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
