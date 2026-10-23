{{-- Operasi of a Perubahan PKPT, before → after (Fase 9.2 Task 7; shared by the Admin and Pimpinan pages since Task 8).
     Input: $operasi (PerubahanPkpt::tampilOperasi rows: op, chip{jenis, irban, jadwal}, baris[{label, sebelum, sesudah}]), $bisaUbah (show Ubah/Hapus).
     Meaning is in text: aksi tag with icon + label, "Sebelum"/"Sesudah" column headers, never color alone (DESIGN §3.5). No animation (§7.5). --}}
@props(['operasi', 'bisaUbah' => false])

@php $ikon = ['tambah' => 'plus', 'ubah' => 'pencil', 'hapus' => 'trash']; @endphp

<ol role="list" class="space-y-3">
    @foreach ($operasi as $o)
        @php $op = $o['op']; $chip = $o['chip']; @endphp
        <li wire:key="operasi-{{ $op->id }}" class="rounded-lg border border-neutral-200 bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 space-y-1.5">
                    <p class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-neutral-600 tabular-nums">Operasi {{ $op->urutan }}</span>
                        <x-app.tag :icon="$ikon[$op->aksi->value]" variant="neutral">{{ $op->aksi->label() }}</x-app.tag>
                    </p>
                    <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-neutral-600">
                        @if ($chip['jenis'])<x-app.type-chip :color="$chip['jenis']->warna" :label="$chip['jenis']->nama" />@endif
                        @if ($chip['irban'])<span>{{ $chip['irban'] }}</span>@endif
                        @if ($chip['jadwal'])<span class="tabular-nums">{{ $chip['jadwal'] }}</span>@endif
                    </p>
                </div>
                @if ($bisaUbah)
                    @php $ctx = 'operasi '.$op->urutan.' ('.$op->aksi->label().')'; @endphp
                    <div class="flex shrink-0 items-center gap-2">
                        @unless ($op->aksi === \App\Enums\AksiPerubahan::Hapus)
                            <x-ui.button type="button" size="sm" variant="secondary" wire:click="bukaOperasi('{{ $op->aksi->value }}', {{ $op->id }})" aria-label="Ubah {{ $ctx }}" class="max-lg:h-11">Ubah</x-ui.button>
                        @endunless
                        <x-ui.button type="button" size="sm" variant="ghost" wire:click="konfirmasiHapusOperasi({{ $op->id }})" aria-label="Hapus {{ $ctx }}" class="!text-danger-700 max-lg:h-11">Hapus</x-ui.button>
                    </div>
                @endif
            </div>

            @if ($o['baris'])
                @php $adaSebelum = $op->aksi !== \App\Enums\AksiPerubahan::Tambah; $adaSesudah = $op->aksi !== \App\Enums\AksiPerubahan::Hapus; @endphp
                <table class="mt-3 w-full table-fixed border-t border-neutral-200 text-sm">
                    <caption class="sr-only">Rincian operasi {{ $op->urutan }}</caption>
                    <thead>
                        <tr class="text-left text-xs text-neutral-600">
                            <th scope="col" class="w-28 py-2 pr-3 font-semibold sm:w-36">Isian</th>
                            @if ($adaSebelum)<th scope="col" class="py-2 pr-3 font-semibold">Sebelum</th>@endif
                            @if ($adaSesudah)<th scope="col" class="py-2 font-semibold">{{ $adaSebelum ? 'Sesudah' : 'Isi item baru' }}</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($o['baris'] as $b)
                            <tr class="border-t border-neutral-100 align-top">
                                <th scope="row" class="py-2 pr-3 text-left font-medium text-neutral-600">{{ $b['label'] }}</th>
                                @if ($adaSebelum)
                                    <td @class(['py-2 pr-3 break-words tabular-nums', 'text-neutral-600' => $adaSesudah, 'text-neutral-900' => ! $adaSesudah])>{{ $b['sebelum'] }}</td>
                                @endif
                                @if ($adaSesudah)
                                    <td class="py-2 break-words font-medium text-neutral-900 tabular-nums">
                                        @if ($adaSebelum){{ svg('heroicon-m-arrow-right', 'mr-1 inline size-4 align-[-2px] text-mint-700', ['aria-hidden' => 'true']) }}@endif{{ $b['sesudah'] }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </li>
    @endforeach
</ol>
