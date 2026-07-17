<x-mail::message>
# New Leave Application

{{ $leaveApplication->user->name }} has submitted a leave application that requires your review.

- **Leave Type:** {{ $leaveApplication->leaveType->name }}
- **Dates:** {{ $leaveApplication->start_date->format('d M Y') }}
@if (! $leaveApplication->start_date->equalTo($leaveApplication->end_date))
 &ndash; {{ $leaveApplication->end_date->format('d M Y') }}
@endif
- **Total Days:** {{ $leaveApplication->total_days }}
- **Reason:** {{ $leaveApplication->reason }}

<x-mail::button :url="route('admin.applications.show', $leaveApplication)">
Review Application
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
