{{-- Riwayat per item (Fase 9.2 Task 9): approved Perubahan PKPT entries of one item from PerubahanPkpt::riwayatItemUntuk(), as a native
     disclosure (keyboard + screen reader for free, no animation, DESIGN §7.5). Per entry "Perubahan ke-N · {d F Y}", aksi with before → after
     dates, "SK {nomor} tanggal {d F Y}" when filled, and "Unduh SK" only when the `unduhSk` gate allows it (`boleh_unduh`). Renders nothing
     without entries. `relative` on rows keeps the sr-only link text from widening a scrolling table (pitfall "sr-only di dalam kontainer gulir"). --}}
@use('App\Enums\AksiPerubahan')
@use('App\Support\Tanggal')
@use('Carbon\CarbonImmutable')
@props(['riwayat' => []])

@php
    $rentang = fn (?array $d) => ! empty($d['tanggal_mulai']) && ! empty($d['tanggal_selesai'])
        ? Tanggal::rentang(CarbonImmutable::parse($d['tanggal_mulai']), CarbonImmutable::parse($d['tanggal_selesai'])) : null;
@endphp

@if (count($riwayat))
    <details {{ $attributes->class('group relative text-sm') }}>
        <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-1 rounded-md font-medium text-mint-700 hover:underline lg:min-h-8 [&::-webkit-details-marker]:hidden">
            {{ svg('heroicon-m-chevron-right', 'size-4 shrink-0 group-open:rotate-90', ['aria-hidden' => 'true']) }}Riwayat perubahan ({{ count($riwayat) }})
        </summary>
        <ol role="list" class="mt-1 space-y-3 border-l border-neutral-200 pl-3">
            @foreach ($riwayat as $r)
                @php
                    $lama = $rentang($r['data_lama']);
                    $baru = $rentang($r['data_baru']);
                    $judul = ($r['nomor_urut'] ? 'Perubahan ke-'.$r['nomor_urut'] : 'Perubahan').($r['disetujui_pada'] ? ' · '.$r['disetujui_pada']->timezone('Asia/Jakarta')->translatedFormat('d F Y') : '');
                @endphp
                <li class="relative">
                    <p class="font-semibold text-neutral-900 tabular-nums">{{ $judul }}</p>
                    <p class="text-neutral-600 tabular-nums">
                        {{ $r['aksi']->label() }}:
                        @if ($r['aksi'] === AksiPerubahan::Hapus)
                            {{ $lama ? $lama.' → dihapus' : 'item dihapus' }}
                        @elseif ($lama && $lama !== $baru)
                            {{ $lama }} → {{ $baru }}
                        @else
                            {{ $baru }}
                        @endif
                    </p>
                    @if ($r['nomor_sk'])
                        <p class="text-neutral-600">SK <span class="tabular-nums text-neutral-900">{{ $r['nomor_sk'] }}</span>@if ($r['tanggal_sk']) tanggal {{ $r['tanggal_sk']->translatedFormat('d F Y') }}@endif</p>
                    @endif
                    @if ($r['boleh_unduh'])
                        <a href="{{ route('perubahan-pkpt.sk', ['perubahan' => $r['perubahan_id'], 'unduh' => 1]) }}"
                            class="mt-0.5 inline-flex min-h-11 items-center gap-1.5 rounded-md font-medium text-mint-700 hover:underline lg:min-h-8">
                            {{ svg('heroicon-m-arrow-down-tray', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}Unduh SK<span class="sr-only"> {{ $judul }}</span>
                        </a>
                    @endif
                </li>
            @endforeach
        </ol>
    </details>
@endif
