<x-mail::message>
# {{ $penalty->isCash() ? 'Cash Penalty' : 'Warning' }} Issued

Dear {{ $penalty->user->name }},

This is to notify you that a **{{ $penalty->isCash() ? 'cash penalty' : 'warning' }}** ({{ $penalty->levelLabel() }}) has been recorded against you.

- **Date:** {{ $penalty->penalty_date->format('d M Y') }}
- **Mistake:** {{ $penalty->mistake }}
- **Level:** {{ $penalty->levelLabel() }}
@if ($penalty->isCash())
- **Charges:** RM {{ number_format((float) $penalty->penalty_charges, 2) }}
@endif
@if ($penalty->remarks)
- **Remarks:** {{ $penalty->remarks }}
@endif

@if ($penalty->isCash())
Please arrange to settle the cash penalty with the administrator.
@endif

<x-mail::button :url="route('penalties.index')">
View My Penalties
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
