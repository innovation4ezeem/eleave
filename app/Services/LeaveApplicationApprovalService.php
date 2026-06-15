<?php

namespace App\Services;

use App\Mail\LeaveApplicationDecided;
use App\Models\LeaveApplication;
use App\Models\User;
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
