<?php

namespace App\Console\Commands;

use App\Models\LeaveYearLock;
use App\Models\User;
use App\Services\LeaveYearRolloverService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leave:rollover {leave_year : The new leave year to roll Carry Forward Leave into, e.g. 2026 for the 1 Jan 2026 - 31 Dec 2026 leave year}')]
#[Description('Carry forward up to 5 days of unused Annual Leave from the previous leave year into Carry Forward Leave for the given leave year')]
class LeaveRolloverCommand extends Command
{
    public function __construct(private LeaveYearRolloverService $rolloverService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $leaveYear = (int) $this->argument('leave_year');
        $previousLeaveYear = $leaveYear - 1;

        $this->info("Rolling over unused Annual Leave from {$previousLeaveYear} into Carry Forward Leave for {$leaveYear}...");

        $results = $this->rolloverService->rolloverAll($leaveYear);

        foreach ($results as $userId => $days) {
            $user = User::find($userId);
            $this->line(" - {$user->name}: {$days} day(s) carried forward");
        }

        $this->info(count($results).' user(s) processed.');

        // Archive the leave year that just ended: lock it read-only.
        LeaveYearLock::updateOrCreate(
            ['leave_year' => $previousLeaveYear],
            ['locked_at' => now(), 'locked_by' => null],
        );

        $this->info("Leave year {$previousLeaveYear} locked (archived).");

        return self::SUCCESS;
    }
}
