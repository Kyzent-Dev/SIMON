{{-- admin.md "Klasifikasi & Aturan": the five fixed classifications; rules are edited in a drawer. No create/delete.
     "Warna Rekap" (spec 1.1.4 Task 4) is data, not a design token: swatch and ribbon preview use --type/--type-fg (DESIGN §13) and always show the hex as text. --}}
@php $yesNo = fn (bool $v) => $v ? ['check-circle', 'Ya', 'text-success-700'] : ['minus-circle', 'Tidak', 'text-neutral-600']; @endphp
<div>
    <x-ui.page-header title="Klasifikasi & Aturan" description="Lima klasifikasi SPT bersifat tetap. Admin hanya mengubah aturannya." />

    <x-ui.table label="Daftar klasifikasi SPT">
        <x-slot:head>
            <x-ui.th>Klasifikasi</x-ui.th>
            <x-ui.th>Wajib item PKPT</x-ui.th>
            <x-ui.th>Syarat selesai</x-ui.th>
            <x-ui.th>Tampil di publik</x-ui.th>
            <x-ui.th>Warna Rekap Tahunan</x-ui.th>
            <x-ui.th><span class="sr-only">Aksi</span></x-ui.th>
        </x-slot:head>
        @foreach ($rows as $row)
            <tr wire:key="klasifikasi-{{ $row->id }}">
                <td class="font-medium whitespace-nowrap">{{ $row->nama }}</td>
                @php [$icon, $text, $color] = $yesNo($row->wajib_item_pkpt); @endphp
                <td><span class="inline-flex items-center gap-1.5 {{ $color }}">{{ svg('heroicon-m-'.$icon, 'size-5') }}{{ $text }}</span></td>
                <td class="min-w-56">{{ $row->syarat_selesai->label() }}</td>
                @php [$icon, $text, $color] = $yesNo($row->tampil_publik); @endphp
                <td><span class="inline-flex items-center gap-1.5 {{ $color }}">{{ svg('heroicon-m-'.$icon, 'size-5') }}{{ $text }}</span></td>
                <td class="whitespace-nowrap">
                    @if ($p = \App\Livewire\Admin\Klasifikasi::pratinjau($row->warna))
                        <span class="inline-flex items-center gap-2 tabular-nums"><span aria-hidden="true" class="size-5 shrink-0 rounded-sm border border-neutral-300 bg-[var(--type)]" style="--type: {{ $p['warna'] }}"></span>{{ $p['warna'] }}</span>
                    @else
                        <span class="text-neutral-600">Belum diatur</span>
                    @endif
                </td>
                <td class="text-right">
                    <x-ui.button type="button" variant="secondary" icon="pencil-square" wire:click="edit({{ $row->id }})" aria-label="Ubah aturan {{ $row->nama }}">Ubah</x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <x-ui.drawer name="klasifikasi-form" title="Ubah aturan klasifikasi" size="md" wire:model="open">
        <form novalidate id="klasifikasi-form" wire:submit="save" class="space-y-5">
            <p class="text-sm text-neutral-600">Klasifikasi: <strong class="font-semibold text-neutral-900">{{ $editing?->nama }}</strong></p>
            <x-ui.switch label="Wajib item PKPT" name="wajibItemPkpt" wire:model="wajibItemPkpt" description="SPT klasifikasi ini harus terhubung ke item PKPT." />
            <x-ui.select label="Syarat selesai" name="syaratSelesai" wire:model="syaratSelesai" required :error="$errors->first('syaratSelesai')"
                :options="\App\Enums\SyaratSelesai::options()" />
            <x-ui.switch label="Tampil di publik" name="tampilPublik" wire:model="tampilPublik" description="Hanya SPT berstatus Selesai yang tampil." />
            <div>
                <x-ui.input label="Warna Rekap Tahunan (hex)" name="warna" wire:model.live.debounce.400ms="warna" autocomplete="off" maxlength="7" placeholder="BDD7EE" class="tabular-nums uppercase"
                    helper="6 digit heksadesimal tanpa #. Mewarnai pita tanggal dan legenda di Excel Rekap Tahunan. Kosongkan bila tidak diwarnai." :error="$errors->first('warna')" />
                <p class="mt-3 mb-2 text-sm font-medium text-neutral-900">Pratinjau pita</p>
                @if ($pratinjau)
                    <span class="flex h-7 w-full max-w-72 items-center justify-center rounded-sm border border-neutral-300 bg-[var(--type)] px-2 text-sm font-semibold text-[var(--type-fg)] tabular-nums"
                        style="--type: {{ $pratinjau['warna'] }}; --type-fg: {{ $pratinjau['teks'] }}">03 s.d 07 Februari 2025</span>
                    <p class="mt-1.5 text-xs text-neutral-600 tabular-nums">{{ $pratinjau['warna'] }} · teks {{ $pratinjau['teks'] === '#FFFFFF' ? 'putih' : 'hitam' }}</p>
                @else
                    <p class="text-sm text-neutral-600">{{ trim($warna) === '' ? 'Tanpa warna: pita tidak diwarnai.' : 'Pratinjau muncul setelah warna valid.' }}</p>
                @endif
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'klasifikasi-form' })">Batal</x-ui.button>
            <x-ui.button form="klasifikasi-form" wire:loading.attr="disabled" wire:target="save">Simpan perubahan</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
