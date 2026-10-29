{{-- Non-blocking duplicate Nomor SPPD warning (versi 1.1.4 Task 2) from UbahNomorSppd::duplikat(). The live region is always rendered so the
     warning is announced when it appears; the field links to it with aria-describedby="{id}". No animation (§7.5). --}}
@props(['id', 'duplikat' => null, 'tahun'])

<div id="{{ $id }}" role="status" aria-live="polite" {{ $attributes }}>
    @if ($duplikat)
        <x-ui.alert variant="warning" class="mt-2">
            <p>Nomor SPPD ini juga tercatat pada
                @if ($duplikat->nomor_lengkap)SPT <span class="tabular-nums [overflow-wrap:anywhere]">{{ $duplikat->nomor_lengkap }}</span>@else SPT lain @endif
                tahun {{ $tahun }}. Pastikan bukan salah ketik; nomor tetap dapat disimpan.</p>
        </x-ui.alert>
    @endif
</div>
