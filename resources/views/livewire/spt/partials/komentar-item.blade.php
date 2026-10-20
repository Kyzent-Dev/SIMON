{{-- One comment's author line + time + body (plain text, escaped, newlines kept). `$k` = Komentar with penulis/diinputOleh loaded. --}}
@php $t = $k->created_at->setTimezone('Asia/Jakarta'); @endphp
<div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
    <p id="komentar-{{ $k->id }}-penulis" class="text-sm font-semibold text-neutral-900">{{ $k->penulis->name }}</p>
    <p class="text-xs font-medium text-neutral-600">{{ $k->penulis->role->label() }}</p>
    <time datetime="{{ $t->toIso8601String() }}" title="{{ $t->translatedFormat('d F Y H.i') }} WIB" class="text-xs font-medium text-neutral-500">{{ $k->created_at->diffForHumans() }}</time>
</div>
@if ($k->diinputOleh)
    <p class="text-xs text-neutral-600">Diinput oleh {{ $k->diinputOleh->nama_bergelar }}</p>
@endif
<p class="mt-1 whitespace-pre-line break-words text-sm text-neutral-900">{{ $k->isi }}</p>
