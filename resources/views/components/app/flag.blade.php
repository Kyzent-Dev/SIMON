{{-- DESIGN §3.6 flag: rectangle (rounded-sm = 4px), outline style, 12px icon + label, so it never reads as a status pill. `jenis` = terlambat | tidak_sesuai_pkpt |
     spt_final_belum_diupload | non_pkpt. Colors are @theme tokens (app.css); classes are literal so Tailwind sees them. --}}
@props(['jenis'])

@php
    [$label, $icon, $warna] = [
        'terlambat' => ['Terlambat', 'exclamation-triangle', 'bg-flag-terlambat-bg text-flag-terlambat-fg'],
        'tidak_sesuai_pkpt' => ['Tidak Sesuai PKPT', 'arrows-right-left', 'bg-flag-tidak_sesuai_pkpt-bg text-flag-tidak_sesuai_pkpt-fg'],
        'spt_final_belum_diupload' => ['SPT Final Belum Diupload', 'arrow-up-tray', 'bg-flag-spt_final_belum_diupload-bg text-flag-spt_final_belum_diupload-fg'],
        'non_pkpt' => ['Non-PKPT', 'minus-circle', 'bg-flag-non_pkpt-bg text-flag-non_pkpt-fg'],
    ][$jenis];
@endphp

<span {{ $attributes->class(['inline-flex h-6 items-center gap-1 whitespace-nowrap rounded-sm border border-current px-2 text-xs font-semibold', $warna]) }}>
    {{ svg('heroicon-o-'.$icon, 'size-3 shrink-0', ['aria-hidden' => 'true']) }}{{ $label }}
</span>
