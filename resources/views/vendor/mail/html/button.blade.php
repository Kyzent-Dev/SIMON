@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
{{-- Bulletproof button: the theme also colours the cell (.button-cell), so clients that drop <a> padding still show it. --}}
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="button-cell">
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
