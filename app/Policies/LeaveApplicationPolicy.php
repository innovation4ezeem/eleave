<?php

namespace App\Policies;

use App\Models\LeaveApplication;
use App\Models\LeaveYearLock;
use App\Models\User;

class LeaveApplicationPolicy
{
    public function view(User $user, LeaveApplication $leaveApplication): bool
    {
        return $user->id === $leaveApplication->user_id
            || $user->isAdmin()
            || ($user->isHod() && $user->department_id === $leaveApplication->department_id);
    }

    public function viewDocument(User $user, LeaveApplication $leaveApplication): bool
    {
        return $this->view($user, $leaveApplication);
    }

    public function cancel(User $user, LeaveApplication $leaveApplication): bool
    {
        return $user->id === $leaveApplication->user_id
            && $leaveApplication->status === 'pending'
            && ! LeaveYearLock::isLocked($leaveApplication->leave_year);
    }

    public function decide(User $user, LeaveApplication $leaveApplication): bool
    {
        return $leaveApplication->status === 'pending'
            && $user->canApproveFor($leaveApplication->department);
    }

    public function requestChange(User $user, LeaveApplication $leaveApplication): bool
    {
        return $user->id === $leaveApplication->user_id
            && in_array($leaveApplication->status, ['approved', 'rejected'], true)
            && ! LeaveYearLock::isLocked($leaveApplication->leave_year);
    }

    public function decideChange(User $user, LeaveApplication $leaveApplication): bool
    {
        return $leaveApplication->status === 'change_requested'
            && $user->canApproveFor($leaveApplication->department);
    }
}
