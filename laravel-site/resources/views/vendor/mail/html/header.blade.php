@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            <img src="{{ asset('assets/company-logo.png') }}" width="180" alt="{{ config('app.name', 'Travel With You') }}" style="display: block; width: 180px; height: auto; max-height: none; margin: 0 auto;">
        </a>
    </td>
</tr>
