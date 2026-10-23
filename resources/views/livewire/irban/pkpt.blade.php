{{-- Irban "Jadwal PKPT" (irban.md, _shared.md tables). Only this irban's active items of a Disahkan PKPT.
     ?tampilan=kalender (default: Wilayah panel + x-app.kalender-pkpt) | tabel: table ≥1024, cards below (DESIGN §11).
     No animation on the table or cards (DESIGN §7.5). --}}
@php
    $tahun = (int) $this->tahun;
    $disahkan = $pkpt?->status === \App\Enums\StatusPkpt::Disahkan;
@endphp
<div>
    <x-ui.page-header title="Jadwal PKPT" :description="'Item PKPT '.$irbanNama.' yang sudah disahkan. Perubahan jadwal berlaku setelah Perubahan PKPT disetujui pimpinan.'">
        @if ($disahkan)
            <x-slot:meta>
                @php $tag = $pkpt->status->tag(); @endphp
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-neutral-600">
                    <x-app.tag :icon="$tag['icon']" :variant="$tag['variant']">{{ $pkpt->status->label() }}</x-app.tag>
                    @if ($pkpt->nomor_sk)
                        <span>SK Bupati <span class="tabular-nums text-neutral-900">{{ $pkpt->nomor_sk }}</span>@if ($pkpt->tanggal_sk), {{ $pkpt->tanggal_sk->translatedFormat('d F Y') }}@endif</span>
                    @endif
                </div>
            </x-slot:meta>
        @endif
        <x-slot:actions>
            <x-ui.segmented name="tampilan" legend="Tampilan" legend-visible :options="['kalender' => 'Kalender', 'tabel' => 'Tabel']" :value="$this->tampilan"
                wire:model.live="tampilan" penuh class="w-full sm:w-auto" />
            <div class="w-full sm:w-40">
                <x-ui.select label="Tahun" name="tahun" wire:model.live="tahun" :options="$tahunList" :value="$this->tahun" />
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div wire:loading.class="opacity-60" wire:target="tahun,tampilan">
        @if (! $pkpt)
            <x-ui.card>
                <x-ui.empty-state icon="calendar-days" :title="'Belum ada PKPT tahun '.$tahun.'.'" description="PKPT disusun dan disahkan oleh Admin. Pilih tahun lain atau hubungi Admin." />
            </x-ui.card>
        @elseif (! $disahkan)
            <x-ui.card>
                <x-ui.empty-state icon="pencil-square" :title="'PKPT tahun '.$tahun.' belum disahkan.'" description="Jadwal dapat dilihat setelah Admin mengesahkan PKPT." />
            </x-ui.card>
        @elseif ($items->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="calendar-days" :title="'Tidak ada item PKPT untuk '.$irbanNama.' tahun '.$tahun.'.'" description="Hubungi Admin bila item untuk irban ini seharusnya ada." />
            </x-ui.card>
        @elseif ($this->tampilan === 'kalender')
            @include('livewire.irban.partials.wilayah', ['w' => $wilayah])
            <x-app.kalender-pkpt :kalender="$kalender" :tahun="$tahun" />
        @else
            {{-- ≥1024: table --}}
            <x-ui.table :label="'Item PKPT '.$irbanNama.' '.$tahun" class="max-lg:hidden">
                <x-slot:head>
                    <x-ui.th>Jenis Penugasan</x-ui.th>
                    <x-ui.th>Jadwal berlaku</x-ui.th>
                    <x-ui.th>Keterangan</x-ui.th>
                </x-slot:head>
                @foreach ($items as $item)
                    @php [$mulai, $selesai] = $rentang[$item->id]; @endphp
                    <tr wire:key="t-{{ $item->id }}">
                        <td class="min-w-40 align-top !py-3"><x-app.type-chip :color="$item->jenisPenugasan->warna" :label="$item->jenisPenugasan->nama" /></td>
                        <td class="min-w-56 align-top !py-3">
                            <x-app.jadwal-berlaku :item="$item" :mulai="$mulai" :selesai="$selesai" />
                        </td>
                        <td class="min-w-28 align-top !py-3">@if ($item->keterangan){{ $item->keterangan }}@else<span class="text-neutral-500">—</span>@endif</td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{-- <1024: cards --}}
            <ul class="space-y-3 lg:hidden" aria-label="Item PKPT {{ $irbanNama }} {{ $tahun }}">
                @foreach ($items as $item)
                    @php [$mulai, $selesai] = $rentang[$item->id]; @endphp
                    <li wire:key="c-{{ $item->id }}" class="rounded-lg border border-neutral-200 bg-white p-4 text-sm text-neutral-900 shadow-xs">
                        <x-app.type-chip :color="$item->jenisPenugasan->warna" :label="$item->jenisPenugasan->nama" />
                        <div class="mt-3 tabular-nums"><x-app.jadwal-berlaku :item="$item" :mulai="$mulai" :selesai="$selesai" /></div>
                        @if ($item->keterangan)
                            <p class="mt-2 text-neutral-600">{{ $item->keterangan }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
