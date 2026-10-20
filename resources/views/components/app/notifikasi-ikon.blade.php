{{-- Icon tile for a notification `jenis` (JenisNotifikasi::ikon); tones use only the verified status-badge pairs (DESIGN §3.4/§3.6). Shared by the bell and the list page. --}}
@props(['jenis'])
@php
    $ikon = \App\Support\JenisNotifikasi::ikon((string) $jenis);
    $nada = [
        'info' => 'bg-status-siap_dilaksanakan-bg text-status-siap_dilaksanakan-fg',
        'success' => 'bg-status-selesai-bg text-status-selesai-fg',
        'warning' => 'bg-status-menunggu_nomor-bg text-status-menunggu_nomor-fg',
        'danger' => 'bg-status-ditolak-bg text-status-ditolak-fg',
        'neutral' => 'bg-neutral-100 text-neutral-600',
    ][$ikon['nada']];
@endphp
<span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $nada }}" aria-hidden="true">{{ svg('heroicon-o-'.$ikon['ikon'], 'size-6') }}</span>
