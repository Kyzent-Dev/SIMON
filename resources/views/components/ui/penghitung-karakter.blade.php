{{-- Character counter under a textarea bound with wire:model="{{ $model }}". Counts code points ([...s]) like the server's mb_strlen.
     The visible count is not a live region (it spoke on every key); the sr-only status speaks once per step when the remainder
     crosses `ambang`, 10 and 0, and goes silent again above `ambang`. No animation (DESIGN §7.5). --}}
@props(['model', 'value' => '', 'max' => 1000, 'ambang' => 20])

<div x-data="{ pesan: '', tahap: undefined,
        jumlah() { return [...($wire.{{ $model }} ?? '')].length },
        tahapDari(s) { return s > {{ $ambang }} ? null : s <= 0 ? 0 : s <= 10 ? 10 : {{ $ambang }} } }"
    x-effect="const s = {{ $max }} - jumlah(), t = tahapDari(s); if (tahap !== undefined && t !== tahap) pesan = t === null ? '' : 'Sisa ' + Math.max(s, 0) + ' karakter'; tahap = t">
    <p class="mt-1 text-right text-xs text-neutral-500 tabular-nums" aria-hidden="true" x-text="jumlah() + '/{{ $max }} karakter'">{{ mb_strlen((string) $value) }}/{{ $max }} karakter</p>
    <p class="sr-only" role="status" x-text="pesan"></p>
</div>
