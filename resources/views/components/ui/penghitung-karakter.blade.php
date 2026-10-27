{{-- Character counter under a textarea bound with wire:model="{{ $model }}". The visible count is not a live region (it spoke on every key);
     an sr-only status speaks only from `ambang` characters left. No animation (DESIGN §7.5). --}}
@props(['model', 'value' => '', 'max' => 1000, 'ambang' => 20])

<p class="mt-1 text-right text-xs text-neutral-500 tabular-nums" aria-hidden="true" x-text="($wire.{{ $model }} ?? '').length + '/{{ $max }} karakter'">{{ mb_strlen((string) $value) }}/{{ $max }} karakter</p>
<p class="sr-only" role="status" x-text="((n) => n <= {{ $ambang }} ? 'Sisa ' + n + ' karakter' : '')({{ $max }} - ($wire.{{ $model }} ?? '').length)"></p>
