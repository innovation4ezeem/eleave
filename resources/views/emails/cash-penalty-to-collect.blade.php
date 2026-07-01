<x-mail::message>
# Cash Penalty to Collect

A cash penalty has been issued and requires collection.

- **Employee:** {{ $penalty->user->name }}@if ($penalty->user->employee_no) ({{ $penalty->user->employee_no }})@endif
- **Amount:** RM {{ number_format((float) $penalty->penalty_charges, 2) }}
- **Date:** {{ $penalty->penalty_date->format('d M Y') }}
- **Level:** {{ $penalty->levelLabel() }}
- **Mistake:** {{ $penalty->mistake }}
- **Reported By:** {{ $penalty->reported_by }}

<x-mail::button :url="route('admin.penalties.show', $penalty)">
View Penalty
</x-mail::button>

Once collected, mark it as collected on the penalty record.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
