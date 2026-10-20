{{-- Body of every NotifikasiSimon email: no greeting or sign-off (design/dashboard/_shared.md §2). --}}
<x-mail::message>
@foreach ($introLines as $line)
{{ $line }}

@endforeach
@if (filled($catatan))
> Catatan: {{ str_replace(["\r\n", "\n", "\r"], ' ', $catatan) }}

@endif
@isset($actionText)
<x-mail::button :url="$actionUrl" align="left">
{{ $actionText }}
</x-mail::button>
@endisset
@isset($actionText)
<x-slot:subcopy>
Jika tombol "{{ $actionText }}" tidak berfungsi, salin dan buka tautan berikut di peramban: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
