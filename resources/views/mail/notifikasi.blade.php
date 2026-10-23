{{-- Body of every NotifikasiSimon email: no greeting or sign-off (design/dashboard/_shared.md §2), except pegawai emails (EmailPegawai), which pass $salam and $penutup (spec K9). --}}
@php
    // User text: backslash-escape Markdown punctuation so it never becomes links or formatting (< > & are left to HTML escaping).
    $esc = fn (string $t) => preg_replace('/[\\\\`*_{}\[\]()#+!|~:-]/', '\\\\$0', str_replace(["\r\n", "\n", "\r"], ' ', $t));
@endphp
<x-mail::message>
@if (filled($salam ?? null))
{{ $esc($salam) }}

@endif
@foreach ($introLines as $line)
{{ $line }}

@endforeach
@if (filled($catatan))
> Catatan: {{ $esc($catatan) }}

@endif
@isset($actionText)
<x-mail::button :url="$actionUrl" align="left">
{{ $actionText }}
</x-mail::button>
@endisset
@if (filled($penutup ?? null))
{{ $esc($penutup) }}

@endif
@isset($actionText)
<x-slot:subcopy>
Jika tombol "{{ $actionText }}" tidak berfungsi, salin dan buka tautan berikut di peramban: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
