{{-- Pimpinan: daftar Perubahan PKPT (Fase 9.2 Task 8; pimpinan.md Approval list, _shared.md §1 page header, §5 empty states; DESIGN §3.4, §7.5).
     Two lists of cards (no wide table, so no horizontal scroll at 360): "Menunggu persetujuan" with a "Tinjau" link, "Riwayat" paginated.
     Status is the rectangular x-app.tag (icon + label). No animation. --}}
@php
    $tgl = fn ($d) => $d?->timezone('Asia/Jakarta')->translatedFormat('d F Y');
    $judul = fn ($p) => 'Perubahan PKPT '.$p->pkpt->tahun.($p->nomor_urut ? ' · Perubahan ke-'.$p->nomor_urut : '');
@endphp
<div>
    <x-ui.page-header title="Perubahan PKPT" description="Setujui atau tolak perubahan jadwal PKPT yang diajukan Admin." />

    <section aria-labelledby="judul-menunggu">
        <h2 id="judul-menunggu" class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Menunggu persetujuan <span class="font-sans text-base font-medium text-neutral-600 tabular-nums">({{ $menunggu->count() }})</span></h2>
        @if ($menunggu->isEmpty())
            <x-ui.empty-state class="mt-3 rounded-lg border border-dashed border-neutral-300 bg-white" icon="check-circle" title="Tidak ada perubahan PKPT yang menunggu persetujuan." />
        @else
        <ul role="list" class="mt-3 space-y-3">
            @foreach ($menunggu as $p)
            <li wire:key="menunggu-{{ $p->id }}" class="rounded-lg border border-neutral-200 bg-white p-4 shadow-xs lg:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-1.5">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="text-base font-semibold text-neutral-900">{{ $judul($p) }}</span>
                            <x-app.tag icon="clock" variant="warning">Diajukan</x-app.tag>
                        </p>
                        <p class="text-sm text-neutral-600">{{ $p->jenis->label() }} · <span class="tabular-nums">{{ $p->operasi_count }}</span> operasi · diajukan <span class="tabular-nums">{{ $tgl($p->diajukan_pada) }}</span> oleh {{ $p->pembuat?->name ?? 'Admin' }}</p>
                        <p class="line-clamp-2 break-words text-sm text-neutral-900">{{ $p->alasan }}</p>
                    </div>
                    <a href="{{ route('pimpinan.perubahan-pkpt.show', $p) }}" wire:navigate
                        class="inline-flex h-11 shrink-0 items-center justify-center rounded-md bg-mint-700 px-4 text-sm font-medium text-white hover:bg-mint-800 max-sm:w-full lg:h-10">Tinjau<span class="sr-only"> {{ $judul($p) }}</span></a>
                </div>
            </li>
            @endforeach
        </ul>
        @endif
    </section>

    <section aria-labelledby="judul-riwayat" class="mt-8">
        <h2 id="judul-riwayat" class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Riwayat</h2>
        @if ($riwayat->isEmpty())
            <p class="mt-3 rounded-md border border-dashed border-neutral-300 bg-white p-4 text-sm text-neutral-600">Belum ada perubahan yang diputuskan.</p>
        @else
            <ul role="list" class="mt-3 space-y-3">
                @foreach ($riwayat as $p)
                    @php $tag = $p->status->tag(); @endphp
                    <li wire:key="riwayat-{{ $p->id }}" class="rounded-lg border border-neutral-200 bg-white p-4 shadow-xs">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 space-y-1">
                                <p class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('pimpinan.perubahan-pkpt.show', $p) }}" wire:navigate class="text-base font-semibold text-mint-700 hover:underline">{{ $judul($p) }}</a>
                                    <x-app.tag :icon="$tag['icon']" :variant="$tag['variant']">{{ $p->status->label() }}</x-app.tag>
                                </p>
                                <p class="text-sm text-neutral-600">{{ $p->jenis->label() }} · <span class="tabular-nums">{{ $p->operasi_count }}</span> operasi · diputuskan <span class="tabular-nums">{{ $tgl($p->diputuskan_pada) }}</span></p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <x-ui.pagination :paginator="$riwayat" :per-page-options="[]" class="mt-4" />
        @endif
    </section>
</div>
