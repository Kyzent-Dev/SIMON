{{-- DESIGN §3.6 status badge: pill (rounded-full), 24px, px-2.5, 12px/600, 14px leading icon, 1px border. Always icon + label (never color alone).
     "Selesai" is the only solid badge (solid check icon); "Dibatalkan" has a dashed border. Colors are @theme tokens (app.css); classes are literal so Tailwind sees them. --}}
@props(['status'])

@php
    // A StatusPengajuan (Register) maps onto its StatusSpt twin; Diterima has none and borrows the blue "On Process" look with its own icon.
    $diterima = $status === \App\Enums\StatusPengajuan::Diterima;
    if ($status instanceof \App\Enums\StatusPengajuan) {
        $status = $diterima ? \App\Enums\StatusSpt::SiapDilaksanakan : \App\Enums\StatusSpt::from($status->value);
    }
    $warna = [
        'draf' => 'bg-status-draf-bg text-status-draf-fg border-status-draf-bd',
        'menunggu_nomor' => 'bg-status-menunggu_nomor-bg text-status-menunggu_nomor-fg border-status-menunggu_nomor-bd',
        'perlu_revisi' => 'bg-status-perlu_revisi-bg text-status-perlu_revisi-fg border-status-perlu_revisi-bd',
        'ditolak' => 'bg-status-ditolak-bg text-status-ditolak-fg border-status-ditolak-bd',
        'siap_dilaksanakan' => 'bg-status-siap_dilaksanakan-bg text-status-siap_dilaksanakan-fg border-status-siap_dilaksanakan-bd',
        'pelaksanaan' => 'bg-status-pelaksanaan-bg text-status-pelaksanaan-fg border-status-pelaksanaan-bd',
        'penyusunan_laporan' => 'bg-status-penyusunan_laporan-bg text-status-penyusunan_laporan-fg border-status-penyusunan_laporan-bd',
        'selesai' => 'bg-status-selesai-bg text-status-selesai-fg border-status-selesai-bd',
        'dibatalkan' => 'bg-status-dibatalkan-bg text-status-dibatalkan-fg border-status-dibatalkan-bd border-dashed',
    ][$status->value];
    $icon = $diterima ? 'heroicon-m-check-badge' : ($status === \App\Enums\StatusSpt::Selesai ? 'heroicon-s-' : 'heroicon-m-').$status->icon();
@endphp

<span {{ $attributes->class(['inline-flex h-6 items-center gap-1 whitespace-nowrap rounded-full border px-2.5 text-xs font-semibold', $warna]) }}>
    {{ svg($icon, 'size-3.5 shrink-0', ['aria-hidden' => 'true']) }}{{ $diterima ? 'Diterima' : $status->label() }}
</span>
