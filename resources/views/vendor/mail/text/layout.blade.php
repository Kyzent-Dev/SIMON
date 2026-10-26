{!! strip_tags($header ?? '') !!}

{{-- Plain text is not rendered as Markdown: undo the escapes NotifikasiSimon::aman() added. --}}
{!! \App\Notifications\NotifikasiSimon::tanpaEscape(strip_tags($slot)) !!}
@isset($subcopy)

{!! strip_tags($subcopy) !!}
@endisset

{!! strip_tags($footer ?? '') !!}
