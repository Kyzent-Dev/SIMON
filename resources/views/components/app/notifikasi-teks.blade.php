{{-- Text of one notification (inside its link): judul, unread dot + sr-only marker, konteks (tnum), optional catatan, relative time with the absolute WIB time in `title`. Shared by the bell and the list page. --}}
@props(['n', 'catatan' => true])
@php
    $d = $n->data;
    $waktu = $n->created_at->setTimezone('Asia/Jakarta');
    // A token with "/" (the SPT number) may wrap only after a slash, never inside a segment at a hyphen.
    $konteks = collect(preg_split('~(\S*/\S*)~u', (string) ($d['konteks'] ?? ''), -1, PREG_SPLIT_DELIM_CAPTURE))
        ->map(fn ($bagian, $i) => $i % 2 === 0 ? e($bagian) : collect(explode('/', $bagian))->map(fn ($seg) => '<span class="whitespace-nowrap">'.e($seg).'</span>')->implode('/<wbr>'))
        ->implode('');
@endphp
<span class="flex items-start gap-2">
    <span class="font-semibold text-neutral-900">{{ $d['judul'] ?? '' }}</span>
    @if ($n->read_at === null)
        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-mint-700" aria-hidden="true"></span><span class="sr-only">Belum dibaca</span>
    @endif
</span>
@if (! empty($d['konteks']))<span class="mt-0.5 block break-words tabular-nums text-neutral-600">{!! $konteks !!}</span>@endif
@if ($catatan && ! empty($d['catatan']))<span class="mt-0.5 line-clamp-2 block break-words text-neutral-600">Catatan: {{ $d['catatan'] }}</span>@endif
<time datetime="{{ $waktu->toIso8601String() }}" title="{{ $waktu->translatedFormat('d F Y H.i') }} WIB" class="mt-1 block text-xs font-medium text-neutral-500">{{ $n->created_at->diffForHumans() }}</time>
