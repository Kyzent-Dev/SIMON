{{-- "Usulan saya" (?tampilan=usulan, Fase 9.2 Task 9): this irban's usulan for the year's PKPT, newest first. Table ≥768, cards below
     (DESIGN §11). Status = x-app.tag (icon + label); catatan penutupan when ditutup, "Perubahan ke-N" when diterapkan. No animation (§7.5). --}}
@use('App\Enums\AksiUsulan')
@use('App\Enums\StatusUsulan')
@use('App\Support\Tanggal')
@php
    $tgl = fn ($d) => $d?->timezone('Asia/Jakarta')->translatedFormat('d F Y');
    // Item column: the item's jenis + current schedule, or the proposed jenis for a new item. Usulan column: aksi + proposed dates.
    $jenisDari = fn ($u) => $u->item?->jenisPenugasan ?? $u->jenisPenugasan;
    $jadwalBaru = fn ($u) => $u->tanggal_mulai && $u->tanggal_selesai && in_array($u->aksi_usulan, [AksiUsulan::Geser, AksiUsulan::Tambah], true)
        ? Tanggal::rentang($u->tanggal_mulai, $u->tanggal_selesai) : null;
@endphp

@if ($usulan->isEmpty())
    <x-ui.card>
        <x-ui.empty-state icon="inbox" :title="'Belum ada usulan perubahan untuk PKPT '.$tahun.'.'"
            description="Pilih &quot;Usulkan Perubahan&quot; pada item di Kalender atau Tabel, atau &quot;Usulkan item baru&quot;." />
    </x-ui.card>
@else
    {{-- ≥768: table --}}
    <x-ui.table :label="'Usulan perubahan '.$irbanNama.' '.$tahun" class="max-md:hidden">
        <x-slot:head>
            <x-ui.th>Diajukan</x-ui.th>
            <x-ui.th>Usulan</x-ui.th>
            <x-ui.th>Item</x-ui.th>
            <x-ui.th>Alasan</x-ui.th>
            <x-ui.th>Status</x-ui.th>
        </x-slot:head>
        @foreach ($usulan as $u)
            @php $j = $jenisDari($u); @endphp
            <tr wire:key="u-t-{{ $u->id }}">
                <td class="whitespace-nowrap align-top !py-3">{{ $tgl($u->created_at) }}</td>
                <td class="min-w-40 align-top !py-3">
                    <p class="font-medium">{{ $u->aksi_usulan->label() }}</p>
                    @if ($b = $jadwalBaru($u))<p class="text-neutral-600">Jadwal baru {{ $b }}</p>@endif
                </td>
                <td class="min-w-48 align-top !py-3">
                    @if ($j)<x-app.type-chip :color="$j->warna" :label="$j->nama" />@endif
                    <p class="mt-1 text-neutral-600">{{ $u->item ? Tanggal::rentang($u->item->tanggal_mulai, $u->item->tanggal_selesai) : 'Item baru' }}</p>
                </td>
                <td class="min-w-56 max-w-96 align-top !py-3"><p class="whitespace-pre-line break-words">{{ $u->alasan }}</p></td>
                <td class="min-w-44 align-top !py-3">@include('livewire.irban.partials.usulan-status', ['u' => $u])</td>
            </tr>
        @endforeach
    </x-ui.table>

    {{-- <768: cards --}}
    <ul class="space-y-3 md:hidden" aria-label="Usulan perubahan {{ $irbanNama }} {{ $tahun }}">
        @foreach ($usulan as $u)
            @php $j = $jenisDari($u); @endphp
            <li wire:key="u-c-{{ $u->id }}" class="rounded-lg border border-neutral-200 bg-white p-4 text-sm text-neutral-900 shadow-xs">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <p class="font-semibold">{{ $u->aksi_usulan->label() }}</p>
                    <p class="text-xs text-neutral-500 tabular-nums">{{ $tgl($u->created_at) }}</p>
                </div>
                <div class="mt-2">
                    @if ($j)<x-app.type-chip :color="$j->warna" :label="$j->nama" />@endif
                    <p class="mt-1 text-neutral-600 tabular-nums">{{ $u->item ? 'Jadwal berlaku '.Tanggal::rentang($u->item->tanggal_mulai, $u->item->tanggal_selesai) : 'Item baru' }}</p>
                    @if ($b = $jadwalBaru($u))<p class="text-neutral-600 tabular-nums">Jadwal baru {{ $b }}</p>@endif
                </div>
                <p class="mt-2 whitespace-pre-line break-words">{{ $u->alasan }}</p>
                <div class="mt-3">@include('livewire.irban.partials.usulan-status', ['u' => $u])</div>
            </li>
        @endforeach
    </ul>
@endif
