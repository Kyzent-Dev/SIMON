{{-- SPT number (tnum) or the pending caption (_shared.md §7: neutral-500 italic). Expects $s. --}}
@if ($s->nomor_lengkap)
    <span class="font-medium tabular-nums text-neutral-900">{{ $s->nomor_lengkap }}</span>
@else
    <span class="italic text-neutral-500">Nomor belum terbit</span>
@endif
