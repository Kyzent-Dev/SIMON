{{-- SPT number (tnum) or the pending caption (_shared.md §7: neutral-500 italic), linking to the detail page. Expects $s. --}}
<a href="{{ route('spt.show', $s) }}" wire:navigate class="relative inline-flex min-h-11 items-center underline-offset-2 hover:underline lg:min-h-6">
    @if ($s->nomor_lengkap)
        <span class="font-medium tabular-nums text-mint-700">{{ $s->nomor_lengkap }}</span>
    @else
        <span class="italic text-neutral-500 underline decoration-neutral-500/60">Nomor belum terbit</span><span class="sr-only">, buka detail {{ \Illuminate\Support\Str::limit($s->uraian_untuk ?? '', 40) }}</span>
    @endif
</a>
