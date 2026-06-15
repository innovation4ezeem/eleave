<?php

namespace App\Policies;

use App\Models\LeaveApplication;
use App\Models\User;

class LeaveApplicationPolicy
{
    public function view(User $user, LeaveApplication $leaveApplication): bool
    {
        return $user->id === $leaveApplication->user_id || $user->isAdmin();
    }

    public function viewDocument(User $user, LeaveApplication $leaveApplication): bool
    {
        return $this->view($user, $leaveApplication);
    }

    public function cancel(User $user, LeaveApplication $leaveApplication): bool
    {
        return $user->id === $leaveApplication->user_id && $leaveApplication->status === 'pending';
    }

    public function decide(User $user, LeaveApplication $leaveApplication): bool
    {
        return $leaveApplication->status === 'pending'
            && $user->canApproveFor($leaveApplication->department);
    }
}
