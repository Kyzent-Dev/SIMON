{{-- Short log line for the SPT detail card and the Admin dashboard: what happened, to what, by whom, when. Read-only. --}}
@props(['a'])

@php
    use App\Support\LabelAktivitas;
    $waktu = LabelAktivitas::waktu($a->created_at);
@endphp
<li {{ $attributes->class('py-3 text-sm') }}>
    <p class="font-medium text-neutral-900">{{ LabelAktivitas::aksi($a->event) }} · {{ LabelAktivitas::objek($a->subject_type, $a->log_name) }}@if ($a->subject_id) <span class="font-normal text-neutral-600">#{{ $a->subject_id }}</span>@endif</p>
    <p class="text-xs text-neutral-600 tabular-nums">{{ $a->causer?->name ?? 'Sistem' }} · {{ $waktu }}</p>
</li>
