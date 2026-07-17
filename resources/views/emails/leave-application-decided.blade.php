<x-mail::message>
# Leave Application {{ ucfirst($leaveApplication->status) }}

Your leave application has been **{{ $leaveApplication->status }}** by {{ $leaveApplication->decidedBy?->name ?? 'an administrator' }}.

- **Leave Type:** {{ $leaveApplication->leaveType->name }}
- **Dates:** {{ $leaveApplication->start_date->format('d M Y') }}
@if (! $leaveApplication->start_date->equalTo($leaveApplication->end_date))
 &ndash; {{ $leaveApplication->end_date->format('d M Y') }}
@endif
- **Total Days:** {{ $leaveApplication->total_days }}
@if ($leaveApplication->remarks)
- **Remarks:** {{ $leaveApplication->remarks }}
@endif

<x-mail::button :url="route('leave.show', $leaveApplication)">
View Application
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
