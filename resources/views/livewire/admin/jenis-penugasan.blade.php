{{-- admin.md "Jenis Penugasan": table with bar + chip preview as rendered publicly (design/public/jadwal.md), drawer with a radio-group color picker. No animation (DESIGN §7.5). Colors only via --type / --type-fg. --}}
<div>
    <x-ui.page-header title="Jenis Penugasan" description="Jenis penugasan dan warnanya di jadwal PKPT dan grafik.">
        <x-slot:actions>
            <x-ui.button type="button" icon="plus" wire:click="create">Tambah jenis penugasan</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="swatch" title="Belum ada jenis penugasan.">
                <x-ui.button type="button" icon="plus" wire:click="create">Tambah jenis penugasan</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table label="Daftar jenis penugasan">
            <x-slot:head>
                <x-ui.th>Jenis penugasan</x-ui.th>
                <x-ui.th>Pratinjau bar</x-ui.th>
                <x-ui.th>Warna</x-ui.th>
                <x-ui.th><span class="sr-only">Aksi</span></x-ui.th>
            </x-slot:head>
            @foreach ($rows as $row)
                <tr wire:key="jenis-{{ $row->id }}">
                    <td class="min-w-48"><x-app.type-chip :color="$row->warna" :label="$row->nama" /></td>
                    <td class="min-w-40">
                        <span class="flex h-[22px] w-40 items-center rounded-md bg-[var(--type)] px-2 text-xs font-semibold text-[var(--type-fg)]" style="--type: {{ $row->warna }}; --type-fg: {{ $row->warna_teks }}">Irban II</span>
                    </td>
                    <td class="whitespace-nowrap tabular-nums">{{ $row->warna }}</td>
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

    <x-ui.drawer name="jenis-form" :title="$editingId ? 'Ubah jenis penugasan' : 'Tambah jenis penugasan'" size="md" wire:model="open">
        <form novalidate id="jenis-form" wire:submit="save" class="space-y-5">
            <x-ui.input label="Nama jenis penugasan" name="nama" wire:model="nama" required autocomplete="off" :error="$errors->first('nama')" />

            <fieldset>
                <legend class="mb-2 text-sm font-medium text-neutral-900">Pilih warna</legend>
                {{-- Native radios sharing one name: arrow keys move and select, Tab enters/leaves the group. --}}
                <div role="radiogroup" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($swatches as $s)
                        <label wire:key="sw-{{ $s['id'] }}" class="relative flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-border-strong bg-white px-2 py-1.5 text-sm text-neutral-900 hover:bg-neutral-50 has-[:checked]:border-2 has-[:checked]:border-mint-700 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-mint-600 has-[:focus-visible]:ring-offset-2">
                            <input type="radio" name="swatch" value="{{ $s['id'] }}" wire:model.live="swatch" class="peer sr-only">
                            <span aria-hidden="true" class="size-6 shrink-0 rounded-full bg-[var(--type)]" style="--type: {{ $s['hex'] }}"></span>
                            <span class="min-w-0 flex-1 leading-tight">{{ $s['nama'] }}</span>
                            <span class="hidden text-mint-700 peer-checked:block">{{ svg('heroicon-m-check-circle', 'size-5') }}</span>
                        </label>
                    @endforeach
                    <label class="relative flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-border-strong bg-white px-2 py-1.5 text-sm text-neutral-900 hover:bg-neutral-50 has-[:checked]:border-2 has-[:checked]:border-mint-700 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-mint-600 has-[:focus-visible]:ring-offset-2">
                        <input type="radio" name="swatch" value="custom" wire:model.live="swatch" class="peer sr-only">
                        <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full border border-dashed border-border-strong text-neutral-600">{{ svg('heroicon-m-plus', 'size-4') }}</span>
                        <span class="min-w-0 flex-1 leading-tight">Warna lain</span>
                        <span class="hidden text-mint-700 peer-checked:block">{{ svg('heroicon-m-check-circle', 'size-5') }}</span>
                    </label>
                </div>
            </fieldset>

            @if ($swatch === 'custom')
                <div>
                    <x-ui.input label="Kode warna (hex)" name="custom" wire:model.live.debounce.400ms="custom" autocomplete="off" maxlength="7" placeholder="#3F6FA6" class="tabular-nums"
                        :error="$errors->first('custom') ?: ($customErrors[0] ?? null)" />
                    @foreach (array_slice($customErrors, 1) as $msg)
                        <p class="mt-1.5 flex items-start gap-1.5 text-sm text-danger-700">{{ svg('heroicon-m-exclamation-circle', 'mt-0.5 size-4 shrink-0') }}{{ $msg }}</p>
                    @endforeach
                    @if ($preview)
                        <p class="mt-1.5 text-xs text-neutral-600">Warna label otomatis: {{ $preview['teks'] === '#FFFFFF' ? 'putih' : 'hitam kehijauan (neutral-900)' }}.</p>
                    @endif
                </div>
            @endif

            @if ($reused)
                <x-ui.alert variant="warning">Warna ini sudah dipakai oleh jenis penugasan lain.</x-ui.alert>
            @endif

            <div>
                <p class="mb-2 text-sm font-medium text-neutral-900">Pratinjau</p>
                @if ($preview)
                    <div class="space-y-3 rounded-md border border-neutral-200 p-3">
                        <x-app.type-chip :color="$preview['warna']" :label="$nama !== '' ? $nama : 'Nama jenis penugasan'" />
                        <span class="flex h-[22px] w-full max-w-60 items-center rounded-md bg-[var(--type)] px-2 text-xs font-semibold text-[var(--type-fg)]" style="--type: {{ $preview['warna'] }}; --type-fg: {{ $preview['teks'] }}">Irban II · Kec. Lawang Kidul</span>
                    </div>
                @else
                    <p class="text-sm text-neutral-600">Pratinjau muncul setelah warna valid.</p>
                @endif
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'jenis-form' })">Batal</x-ui.button>
            <x-ui.button form="jenis-form" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    <x-ui.modal name="jenis-hapus" title="Hapus jenis penugasan?" size="sm" wire:model="deleteOpen">
        <p>Jenis penugasan <strong class="font-semibold">{{ $deleting?->nama }}</strong> akan dihapus permanen.</p>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'jenis-hapus' })">Batal</x-ui.button>
            <x-ui.button type="button" variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Hapus</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
