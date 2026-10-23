{{-- PKPT item range (KesesuaianPkpt::rentang) in full + "Durasi: N hari"; when an approved Perubahan PKPT moved it, the pengesahan snapshot
     (PkptItem::semula) follows as "semula 3–21 Februari 2026" with a small dashed marker echoing the dashed original bar on the public timeline (design/public/jadwal.md). --}}
@use('App\Support\Tanggal')
@props(['item', 'mulai', 'selesai'])

<p class="whitespace-nowrap">{{ $mulai->translatedFormat('j F Y') }} – {{ $selesai->translatedFormat('j F Y') }}</p>
<p class="text-xs text-neutral-500">Durasi: {{ (int) $mulai->diffInDays($selesai) + 1 }} hari</p>
@if ($semula = $item->semula())
    <p class="relative mt-1 flex items-center gap-1.5 text-xs text-neutral-600">
        <span aria-hidden="true" class="inline-block h-2.5 w-4 shrink-0 rounded-sm border border-dashed border-border-strong"></span>
        <span><span class="sr-only">Jadwal direvisi, </span>semula {{ Tanggal::rentang(...$semula) }}</span>
    </p>
@endif
