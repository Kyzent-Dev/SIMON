{{-- Inline item editor cells for the PKPT item table (add row and edit row share them). Inputs bind to `$p.*` and submit the form `$form`
     via the HTML `form` attribute, so Enter works without nesting a <form> in the table. Labels are sr-only: the column headers are the visible labels.
     Inputs inside tables use radius-sm (DESIGN §5). Errors appear instantly under the field, linked by aria-describedby (no animation, §7.5).
     The edit row's first field takes focus when it appears (the "Ubah" button it replaces is gone). Controls carry the min widths so add and edit rows size the same. Cells are `relative` so the absolutely positioned sr-only labels stay inside the table scroller (otherwise they widen the page). --}}
@php
    $field = 'block w-full rounded-sm bg-white text-base text-neutral-900 h-11 lg:h-9 lg:text-sm focus:border-mint-700 focus:ring-2 focus:ring-mint-600';
    $border = fn ($k) => $errors->has("$p.$k") ? 'border-danger-700' : 'border-border-strong';
    $aria = fn ($k) => $errors->has("$p.$k") ? 'aria-invalid="true" aria-describedby="'.$p.'-'.$k.'-error"' : '';
    $error = function ($k) use ($errors, $p) {
        if (! $errors->has("$p.$k")) {
            return '';
        }

        return '<p id="'.$p.'-'.$k.'-error" class="mt-1 flex items-start gap-1 text-xs font-medium text-danger-700 whitespace-normal">'
            .svg('heroicon-m-exclamation-circle', 'size-4 shrink-0')->toHtml().'<span>'.e($errors->first("$p.$k")).'</span></p>';
    };
@endphp
<td class="relative align-top !py-2">
    <label for="{{ $p }}-irban" class="sr-only">Irban ({{ $caption }})</label>
    <select id="{{ $p }}-irban" form="{{ $form }}" @if ($p === 'ubah') x-init="$el.focus()" @endif wire:model="{{ $p }}.irban_id" {!! $aria('irban_id') !!} class="{{ $field }} min-w-28 {{ $border('irban_id') }} pr-8 pl-2">
        <option value="">Pilih irban</option>
        @foreach ($irbans as $id => $nama)<option value="{{ $id }}">{{ $nama }}</option>@endforeach
    </select>
    {!! $error('irban_id') !!}
</td>
<td class="relative align-top !py-2">
    <label for="{{ $p }}-jenis" class="sr-only">Jenis penugasan ({{ $caption }})</label>
    <select id="{{ $p }}-jenis" form="{{ $form }}" wire:model="{{ $p }}.jenis_penugasan_id" {!! $aria('jenis_penugasan_id') !!} class="{{ $field }} min-w-36 {{ $border('jenis_penugasan_id') }} pr-8 pl-2">
        <option value="">Pilih jenis penugasan</option>
        @foreach ($jenis as $id => $nama)<option value="{{ $id }}">{{ $nama }}</option>@endforeach
    </select>
    {!! $error('jenis_penugasan_id') !!}
</td>
<td class="relative align-top !py-2">
    <div class="space-y-1.5">
        <div>
            <label for="{{ $p }}-mulai" class="sr-only">Tanggal mulai ({{ $caption }})</label>
            <input id="{{ $p }}-mulai" type="date" form="{{ $form }}" wire:model="{{ $p }}.tanggal_mulai" min="{{ $tahun }}-01-01" max="{{ $tahun }}-12-31" {!! $aria('tanggal_mulai') !!} class="{{ $field }} min-w-36 {{ $border('tanggal_mulai') }} px-2 tabular-nums">
            {!! $error('tanggal_mulai') !!}
        </div>
        <div>
            <label for="{{ $p }}-selesai" class="sr-only">Tanggal selesai ({{ $caption }})</label>
            <input id="{{ $p }}-selesai" type="date" form="{{ $form }}" wire:model="{{ $p }}.tanggal_selesai" min="{{ $tahun }}-01-01" max="{{ $tahun }}-12-31" {!! $aria('tanggal_selesai') !!} class="{{ $field }} min-w-36 {{ $border('tanggal_selesai') }} px-2 tabular-nums">
            {!! $error('tanggal_selesai') !!}
        </div>
    </div>
</td>
<td class="relative align-top !py-2">
    <label for="{{ $p }}-keterangan" class="sr-only">Keterangan ({{ $caption }})</label>
    <input id="{{ $p }}-keterangan" type="text" form="{{ $form }}" wire:model="{{ $p }}.keterangan" maxlength="255" autocomplete="off" placeholder="Opsional" {!! $aria('keterangan') !!} class="{{ $field }} min-w-28 {{ $border('keterangan') }} px-2">
    {!! $error('keterangan') !!}
</td>
<td class="relative align-top !py-2">
    <label for="{{ $p }}-target" class="sr-only">Target SPT ({{ $caption }})</label>
    <input id="{{ $p }}-target" type="number" inputmode="numeric" min="1" max="999" form="{{ $form }}" wire:model="{{ $p }}.target_spt"
        @if ($errors->has("$p.target_spt")) aria-invalid="true" @endif aria-describedby="{{ $p }}-target-bantuan{{ $errors->has("$p.target_spt") ? ' '.$p.'-target_spt-error' : '' }}"
        class="{{ $field }} min-w-16 {{ $border('target_spt') }} px-2 text-right tabular-nums">
    <p id="{{ $p }}-target-bantuan" class="mt-1 w-24 text-xs whitespace-normal text-neutral-500">Perkiraan jumlah SPT untuk item ini. Kosong dihitung 1.</p>
    {!! $error('target_spt') !!}
</td>
