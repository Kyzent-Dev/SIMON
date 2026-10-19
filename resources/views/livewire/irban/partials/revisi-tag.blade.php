{{-- Latest revision state of $item (revisi ordered oldest first), or a dash. --}}
@if ($last = $item->revisi->last())
    @php $rt = $last->status->tag(); @endphp
    <x-app.tag :icon="$rt['icon']" :variant="$rt['variant']">{{ $last->status->label() }}</x-app.tag>
@else
    <span class="text-neutral-500"><span aria-hidden="true">—</span><span class="sr-only">Belum ada revisi</span></span>
@endif
