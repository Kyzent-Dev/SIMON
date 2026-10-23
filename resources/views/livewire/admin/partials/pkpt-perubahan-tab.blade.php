{{-- Tab "Perubahan" of a Disahkan PKPT (Fase 9.2 Task 7; admin.md PKPT, _shared.md §3 tables, §5 modals). Paket list with the rectangular
     x-app.tag status (pills stay reserved for SPT status), "SK belum dilengkapi" as icon + text, and "Buat Perubahan", disabled with the written
     reason while an open paket exists (PerubahanPkpt::terbuka). No animation on the table (DESIGN §7.5). --}}
@php
    $tgl = fn ($d) => $d?->timezone('Asia/Jakarta')->translatedFormat('d F Y');
    $nama = fn ($p) => $p->nomor_urut ? 'Perubahan ke-'.$p->nomor_urut : 'Belum bernomor';
@endphp

<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <p class="max-w-2xl text-sm text-neutral-600">PKPT yang sudah disahkan hanya diubah lewat paket Perubahan PKPT yang disetujui Pimpinan.</p>
    <div class="flex flex-col gap-1.5 sm:max-w-sm sm:items-end">
        @if ($terbuka)
            <x-ui.button type="button" icon="plus" disabled aria-disabled="true" aria-describedby="alasan-buat-nonaktif" class="max-sm:w-full">Buat Perubahan</x-ui.button>
            <p id="alasan-buat-nonaktif" class="text-sm text-neutral-600 sm:text-right">
                {{ \App\Actions\Pkpt\Perubahan\BuatPerubahan::SUDAH_ADA }}
                <a href="{{ route('admin.pkpt.perubahan', [$pkpt, $terbuka]) }}" wire:navigate class="font-medium text-mint-700 underline">Buka perubahan yang belum selesai</a>
            </p>
        @else
            <x-ui.button type="button" icon="plus" wire:click="$set('buatOpen', true)" class="max-sm:w-full">Buat Perubahan</x-ui.button>
        @endif
    </div>
</div>

@if ($paket->isEmpty())
    <x-ui.card>
        <x-ui.empty-state icon="document-duplicate" title="Belum ada Perubahan PKPT." description="Buat perubahan untuk menambah, menggeser, atau menghapus item PKPT ini." />
    </x-ui.card>
@else
    <x-ui.table label="Perubahan PKPT {{ $pkpt->tahun }}">
        <x-slot:head>
            <x-ui.th>Perubahan</x-ui.th>
            <x-ui.th>Status</x-ui.th>
            <x-ui.th>Jenis</x-ui.th>
            <x-ui.th numeric>Operasi</x-ui.th>
            <x-ui.th>Diajukan</x-ui.th>
            <x-ui.th>Diputuskan</x-ui.th>
            <x-ui.th>SK</x-ui.th>
        </x-slot:head>
        @foreach ($paket as $p)
            @php $tag = $p->status->tag(); @endphp
            <tr wire:key="paket-{{ $p->id }}">
                <td class="relative whitespace-nowrap align-top !py-3">
                    <a href="{{ route('admin.pkpt.perubahan', [$pkpt, $p]) }}" wire:navigate class="font-medium text-mint-700 underline-offset-2 hover:underline">{{ $nama($p) }}<span class="sr-only">, dibuat {{ $tgl($p->created_at) }}</span></a>
                    <p class="text-xs text-neutral-500">Dibuat {{ $tgl($p->created_at) }}</p>
                </td>
                <td class="align-top !py-3"><x-app.tag :icon="$tag['icon']" :variant="$tag['variant']">{{ $p->status->label() }}</x-app.tag></td>
                <td class="whitespace-nowrap align-top !py-3">{{ $p->jenis->label() }}</td>
                <td class="align-top !py-3 text-right">{{ $p->operasi_count }}</td>
                <td class="whitespace-nowrap align-top !py-3">{{ $tgl($p->diajukan_pada) ?? '—' }}</td>
                <td class="whitespace-nowrap align-top !py-3">{{ $tgl($p->diputuskan_pada) ?? '—' }}</td>
                <td class="whitespace-nowrap align-top !py-3">
                    @if ($p->perluSk())
                        <x-app.tag icon="document-arrow-up" variant="warning">SK belum dilengkapi</x-app.tag>
                    @elseif ($p->skLengkap())
                        <span class="inline-flex items-center gap-1 text-sm text-neutral-900">{{ svg('heroicon-m-check', 'size-4 shrink-0 text-mint-700') }}Lengkap</span>
                    @else
                        <span class="text-neutral-500">—</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-ui.table>
@endif

<x-ui.modal name="perubahan-buat" title="Buat Perubahan PKPT" size="md" wire:model="buatOpen">
    <form id="form-buat-perubahan" wire:submit="buatPerubahan" novalidate class="space-y-4">
        <fieldset>
            <legend class="mb-1.5 text-sm font-medium text-neutral-900">Jenis<span class="text-danger-700" aria-hidden="true"> *</span><span class="sr-only"> (wajib)</span></legend>
            <div class="flex flex-wrap gap-x-6">
                @foreach (\App\Enums\JenisPerubahan::cases() as $j)
                    <x-ui.radio name="buat-jenis" :value="$j->value" :label="$j->label()" id="buat-jenis-{{ $j->value }}" wire:model="buat.jenis" />
                @endforeach
            </div>
            @error('buat.jenis')<p class="mt-1.5 text-sm text-danger-700">{{ $message }}</p>@enderror
        </fieldset>
        <x-ui.textarea name="buat-alasan" label="Alasan" required wire:model="buat.alasan" maxlength="2000" :error="$errors->first('buat.alasan')"
            helper="Maksimal 2000 karakter. Tampil untuk Pimpinan saat memutuskan." />
    </form>
    <x-slot:footer>
        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'perubahan-buat' })">Batal</x-ui.button>
        <x-ui.button form="form-buat-perubahan" icon="plus" wire:loading.attr="disabled" wire:target="buatPerubahan">Buat Perubahan</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
