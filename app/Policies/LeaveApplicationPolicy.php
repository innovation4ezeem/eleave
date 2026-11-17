<?php

namespace App\Policies;

use App\Models\LeaveApplication;
use App\Models\LeaveYearLock;
use App\Models\User;
use App\Support\LeaveYear;

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

    /**
     * Whether the user may act on a pending application at all. Rejecting
     * never touches a balance, so it stays allowed in a locked year.
     */
    public function decide(User $user, LeaveApplication $leaveApplication): bool
    {
        return $leaveApplication->status === 'pending'
            && $user->canApproveFor($leaveApplication->department);
    }

    /**
     * Approving deducts the balance of the application's leave year, so it is
     * blocked once that year is locked: the year-end rollover has already
     * turned its unused leave into Carry Forward. An admin has to unlock the
     * year, approve, then re-run the rollover from the Leave Years page.
     * (The Historical Leave tool is deliberately not covered by this.)
     */
    public function approve(User $user, LeaveApplication $leaveApplication): bool
    {
        return $this->decide($user, $leaveApplication)
            && ! LeaveYearLock::isLocked($leaveApplication->leave_year);
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

    /**
     * Approving a change restores the original leave year's balance and (for an
     * amendment) deducts the new one, so neither year may be locked.
     */
    public function approveChange(User $user, LeaveApplication $leaveApplication): bool
    {
        if (! $this->decideChange($user, $leaveApplication)
            || LeaveYearLock::isLocked($leaveApplication->leave_year)) {
            return false;
        }

        if ($leaveApplication->change_action === 'amend' && $leaveApplication->requested_start_date) {
            return ! LeaveYearLock::isLocked(LeaveYear::forDate($leaveApplication->requested_start_date));
        }

        return true;
    }
}
