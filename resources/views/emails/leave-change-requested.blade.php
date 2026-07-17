<x-mail::message>
# Leave {{ $leaveApplication->change_action === 'cancel' ? 'Cancellation' : 'Change' }} Request

{{ $leaveApplication->user->name }} has requested a {{ $leaveApplication->change_action === 'cancel' ? 'cancellation' : 'change' }} for a previously decided leave application.

- **Current Leave Type:** {{ $leaveApplication->leaveType->name }}
- **Current Dates:** {{ $leaveApplication->start_date->format('d M Y') }}
@if (! $leaveApplication->start_date->equalTo($leaveApplication->end_date))
 &ndash; {{ $leaveApplication->end_date->format('d M Y') }}
@endif
- **Current Total Days:** {{ $leaveApplication->total_days }}

@if ($leaveApplication->change_action === 'amend')
- **Requested Leave Type:** {{ $leaveApplication->requestedLeaveType?->name }}
- **Requested Dates:** {{ $leaveApplication->requested_start_date?->format('d M Y') }}
@if ($leaveApplication->requested_start_date && $leaveApplication->requested_end_date && ! $leaveApplication->requested_start_date->equalTo($leaveApplication->requested_end_date))
 &ndash; {{ $leaveApplication->requested_end_date->format('d M Y') }}
@endif
- **Requested Total Days:** {{ $leaveApplication->requested_total_days }}
@endif

<x-mail::button :url="route('admin.applications.show', $leaveApplication)">
Review Request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
