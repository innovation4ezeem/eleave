<?php

namespace App\Services;

use App\Mail\LeaveApplicationDecided;
use App\Mail\LeaveChangeRequested;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Support\LeaveYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LeaveApplicationApprovalService
{
    public function __construct(
        private LeaveBalanceService $balanceService,
    ) {}

    public function approve(LeaveApplication $application, User $admin, ?string $remarks = null): void
    {
        DB::transaction(function () use ($application, $admin, $remarks) {
            $fromStatus = $application->status;

            $application->update([
                'status' => 'approved',
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'remarks' => $remarks,
            ]);

            if ($application->leaveType->affects_balance) {
                $balance = $this->balanceService->getOrCreateBalance(
                    $application->user,
                    $application->leaveType,
                    $application->leave_year,
                );

                $this->balanceService->deduct($balance, (float) $application->total_days);
            }

            $application->logs()->create([
                'actor_id' => $admin->id,
                'action' => 'approved',
                'from_status' => $fromStatus,
                'to_status' => 'approved',
                'notes' => $remarks,
            ]);
        });

        $this->sendDecisionEmail($application);
    }

    public function reject(LeaveApplication $application, User $admin, ?string $remarks = null): void
    {
        DB::transaction(function () use ($application, $admin, $remarks) {
            $fromStatus = $application->status;

            $application->update([
                'status' => 'rejected',
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'remarks' => $remarks,
            ]);

            $application->logs()->create([
                'actor_id' => $admin->id,
                'action' => 'rejected',
                'from_status' => $fromStatus,
                'to_status' => 'rejected',
                'notes' => $remarks,
            ]);
        });

        $this->sendDecisionEmail($application);
    }

    /**
     * @param array{change_action: string, reason: string, leave_type_id?: int, start_date?: string, end_date?: string, start_half?: string, end_half?: string} $data
     */
    public function requestChange(LeaveApplication $application, User $employee, array $data): void
    {
        DB::transaction(function () use ($application, $employee, $data) {
            $fromStatus = $application->status;

            $updates = [
                'status' => 'change_requested',
                'prev_status' => $fromStatus,
                'change_action' => $data['change_action'],
            ];

            if ($data['change_action'] === 'amend') {
                $totalDays = (new LeaveDayCalculator())->calculate(
                    $data['start_date'],
                    $data['end_date'],
                    $data['start_half'],
                    $data['end_half'],
                );

                $updates['requested_leave_type_id'] = $data['leave_type_id'];
                $updates['requested_start_date'] = $data['start_date'];
                $updates['requested_end_date'] = $data['end_date'];
                $updates['requested_start_half'] = $data['start_half'];
                $updates['requested_end_half'] = $data['end_half'];
                $updates['requested_total_days'] = $totalDays;
            }

            $application->update($updates);

            $application->logs()->create([
                'actor_id' => $employee->id,
                'action' => 'change_requested',
                'from_status' => $fromStatus,
                'to_status' => 'change_requested',
                'notes' => $data['reason'],
            ]);
        });

        $this->sendChangeRequestEmail($application);
    }

    public function approveChange(LeaveApplication $application, User $manager): void
    {
        $application->loadMissing(['leaveType', 'requestedLeaveType', 'user']);

        DB::transaction(function () use ($application, $manager) {
            $fromStatus = $application->status;
            $wasApproved = $application->prev_status === 'approved';

            if ($application->change_action === 'cancel') {
                if ($wasApproved && $application->leaveType->affects_balance) {
                    $balance = $this->balanceService->getOrCreateBalance(
                        $application->user,
                        $application->leaveType,
                        $application->leave_year,
                    );

                    $this->balanceService->restore($balance, (float) $application->total_days);
                }

                $application->update([
                    'status' => 'cancelled',
                    'prev_status' => null,
                    'change_action' => null,
                ]);

                $application->logs()->create([
                    'actor_id' => $manager->id,
                    'action' => 'change_approved',
                    'from_status' => $fromStatus,
                    'to_status' => 'cancelled',
                ]);

                return;
            }

            // Amend: restore the old balance deduction (if any), apply the new
            // leave details directly, and deduct the new balance (if applicable).
            $oldLeaveType = $application->leaveType;
            $oldTotalDays = (float) $application->total_days;
            $oldLeaveYear = $application->leave_year;

            $newLeaveType = $application->requestedLeaveType;
            $newTotalDays = (float) $application->requested_total_days;
            $newLeaveYear = LeaveYear::forDate($application->requested_start_date);

            if ($wasApproved && $oldLeaveType->affects_balance) {
                $oldBalance = $this->balanceService->getOrCreateBalance($application->user, $oldLeaveType, $oldLeaveYear);
                $this->balanceService->restore($oldBalance, $oldTotalDays);
            }

            $application->update([
                'leave_type_id' => $newLeaveType->id,
                'start_date' => $application->requested_start_date,
                'end_date' => $application->requested_end_date,
                'start_half' => $application->requested_start_half,
                'end_half' => $application->requested_end_half,
                'total_days' => $newTotalDays,
                'leave_year' => $newLeaveYear,
                'status' => 'approved',
                'prev_status' => null,
                'change_action' => null,
                'requested_leave_type_id' => null,
                'requested_start_date' => null,
                'requested_end_date' => null,
                'requested_start_half' => null,
                'requested_end_half' => null,
                'requested_total_days' => null,
                'decided_by' => $manager->id,
                'decided_at' => now(),
                'remarks' => null,
            ]);

            if ($newLeaveType->affects_balance) {
                $newBalance = $this->balanceService->getOrCreateBalance($application->user, $newLeaveType, $newLeaveYear);
                $this->balanceService->deduct($newBalance, $newTotalDays);
            }

            $application->logs()->create([
                'actor_id' => $manager->id,
                'action' => 'change_approved',
                'from_status' => $fromStatus,
                'to_status' => 'approved',
            ]);
        });

        $this->sendDecisionEmail($application);
    }

    public function rejectChange(LeaveApplication $application, User $manager, ?string $remarks = null): void
    {
        DB::transaction(function () use ($application, $manager, $remarks) {
            $fromStatus = $application->status;
            $revertTo = $application->prev_status ?: 'approved';

            $application->update([
                'status' => $revertTo,
                'prev_status' => null,
                'change_action' => null,
                'requested_leave_type_id' => null,
                'requested_start_date' => null,
                'requested_end_date' => null,
                'requested_start_half' => null,
                'requested_end_half' => null,
                'requested_total_days' => null,
            ]);

            $application->logs()->create([
                'actor_id' => $manager->id,
                'action' => 'change_rejected',
                'from_status' => $fromStatus,
                'to_status' => $revertTo,
                'notes' => $remarks,
            ]);
        });

        $this->sendDecisionEmail($application);
    }

    private function sendChangeRequestEmail(LeaveApplication $application): void
    {
        try {
            $application->loadMissing(['user', 'leaveType', 'requestedLeaveType']);

            $recipients = User::where('role', 'hod')
                ->where('department_id', $application->department_id)
                ->whereNotNull('employee_status')
                ->get();

            if ($recipients->isEmpty()) {
                $recipients = User::where('role', 'admin')->whereNotNull('employee_status')->get();
            }

            if ($recipients->isEmpty()) {
                return;
            }

            Mail::to($recipients->pluck('email')->all())->send(new LeaveChangeRequested($application));
        } catch (\Throwable $e) {
            Log::error('Failed to send leave change request email.', [
                'leave_application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendDecisionEmail(LeaveApplication $application): void
    {
        if ($application->is_historical) {
            return;
        }

        try {
            $application->loadMissing(['user', 'leaveType', 'decidedBy']);

            Mail::to($application->user->email)->send(new LeaveApplicationDecided($application));
        } catch (\Throwable $e) {
            Log::error('Failed to send leave application decision email.', [
                'leave_application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
