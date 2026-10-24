{{-- Status of one usulan ($u): x-app.tag, then "Perubahan ke-N" when diterapkan or the catatan penutupan when ditutup. --}}
@php $tag = $u->status->tag(); @endphp
<x-app.tag :icon="$tag['icon']" :variant="$tag['variant']">{{ $u->status->label() }}</x-app.tag>
@if ($u->status === \App\Enums\StatusUsulan::Diterapkan && $u->perubahan?->nomor_urut)
    <p class="mt-1 text-neutral-600 tabular-nums">Perubahan ke-{{ $u->perubahan->nomor_urut }}</p>
@elseif ($u->status === \App\Enums\StatusUsulan::Ditutup && $u->catatan_penutupan)
    <p class="mt-1 text-xs text-neutral-500">Catatan penutupan</p>
    <p class="whitespace-pre-line break-words text-neutral-900">{{ $u->catatan_penutupan }}</p>
@endif
