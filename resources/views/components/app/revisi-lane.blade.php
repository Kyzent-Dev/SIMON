{{-- Mini timeline for one schedule revision (pimpinan.md Approval list; design/public/jadwal.md "Revised item"): a 12-month lane of the PKPT year
     with the original range as a dashed 2px outline (`--type` at 70%) and the proposed range as a solid `--type` bar on top.
     The lane is decorative (aria-hidden); the legend below states both ranges in text, so meaning never depends on the drawing. No animation. --}}
@use('App\Support\Tanggal')
@props(['revisi', 'color', 'tahun'])

@php
    $awal = \Carbon\CarbonImmutable::create($tahun, 1, 1);
    $hari = $awal->daysInYear;
    // Left offset and width in % of the year; revision dates are always inside the PKPT year (AjukanRevisiJadwal).
    $pos = fn ($m, $s) => sprintf('left: %.3f%%; width: %.3f%%', $awal->diffInDays($m) / $hari * 100, ($awal->diffInDays($s) - $awal->diffInDays($m) + 1) / $hari * 100);
    $durasi = fn ($m, $s) => (int) $m->diffInDays($s) + 1;
@endphp

<div {{ $attributes }} style="--type: {{ $color }}">
    <div aria-hidden="true">
        <div class="relative h-6 rounded-sm bg-neutral-50">
            <div class="absolute inset-0 grid grid-cols-12">
                @for ($b = 1; $b <= 12; $b++)
                    <span class="border-neutral-200 {{ $b > 1 ? 'border-l' : '' }}"></span>
                @endfor
            </div>
            <span class="absolute inset-y-0 min-w-1.5 rounded-sm border-2 border-dashed border-[color-mix(in_srgb,var(--type)_70%,transparent)]"
                style="{{ $pos($revisi->tanggal_mulai_semula, $revisi->tanggal_selesai_semula) }}"></span>
            <span class="absolute inset-y-1.5 min-w-1.5 rounded-sm bg-[var(--type)]" style="{{ $pos($revisi->tanggal_mulai, $revisi->tanggal_selesai) }}"></span>
        </div>
        <div class="mt-1 grid grid-cols-12 text-center text-xs text-neutral-500">
            @for ($b = 1; $b <= 12; $b++)
                @php $bln = $awal->month($b); @endphp
                <span><span class="max-sm:hidden">{{ $bln->translatedFormat('M') }}</span><span class="sm:hidden">{{ mb_substr($bln->translatedFormat('M'), 0, 1) }}</span></span>
            @endfor
        </div>
    </div>
    <dl class="mt-3 grid grid-cols-[auto_1fr] items-baseline gap-x-2 gap-y-1 text-sm tabular-nums">
        <dt class="flex items-center gap-1.5 text-neutral-600">
            <span aria-hidden="true" class="inline-block h-2.5 w-4 shrink-0 rounded-sm border-2 border-dashed border-[color-mix(in_srgb,var(--type)_70%,transparent)]"></span>Semula
        </dt>
        <dd class="text-neutral-900">{{ Tanggal::rentang($revisi->tanggal_mulai_semula, $revisi->tanggal_selesai_semula) }} · {{ $durasi($revisi->tanggal_mulai_semula, $revisi->tanggal_selesai_semula) }} hari</dd>
        <dt class="flex items-center gap-1.5 text-neutral-600">
            <span aria-hidden="true" class="inline-block h-2.5 w-4 shrink-0 rounded-sm bg-[var(--type)]"></span>Usulan
        </dt>
        <dd class="font-medium text-neutral-900">{{ Tanggal::rentang($revisi->tanggal_mulai, $revisi->tanggal_selesai) }} · {{ $durasi($revisi->tanggal_mulai, $revisi->tanggal_selesai) }} hari</dd>
    </dl>
</div>
