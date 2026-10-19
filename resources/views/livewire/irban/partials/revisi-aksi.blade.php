{{-- "Ajukan Revisi Jadwal" for $item; disabled with a visible reason while a revision is pending. $v keeps ids unique between table and cards. --}}
@php $label = 'Ajukan Revisi Jadwal: '.$item->jenisPenugasan->nama.', '.\App\Support\Tanggal::rentang(...$rentang[$item->id]); @endphp
@if ($item->revisi->contains('status', \App\Enums\StatusRevisi::Menunggu))
    <x-ui.button type="button" size="sm" variant="secondary" disabled aria-label="{{ $label }}" aria-describedby="menunggu-{{ $v }}-{{ $item->id }}" class="whitespace-nowrap max-lg:h-11">Ajukan Revisi Jadwal</x-ui.button>
    <p id="menunggu-{{ $v }}-{{ $item->id }}" class="mt-1 ml-auto max-w-40 text-xs whitespace-normal text-neutral-600 max-lg:ml-0 max-lg:max-w-none max-lg:text-left">Masih ada revisi yang menunggu persetujuan.</p>
@else
    <x-ui.button type="button" size="sm" variant="secondary" wire:click="buka({{ $item->id }})" aria-label="{{ $label }}" class="whitespace-nowrap max-lg:h-11">Ajukan Revisi Jadwal</x-ui.button>
@endif
