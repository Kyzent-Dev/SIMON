@props(['url'])
<tr>
<td class="header-wrap" align="center">
<table class="header" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="header-cell">
<a href="{{ $url }}" style="display: inline-block;">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
