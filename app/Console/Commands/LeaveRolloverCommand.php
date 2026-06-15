<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LeaveYearRolloverService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leave:rollover {leave_year : The new leave year to roll Carry Forward Leave into, e.g. 2026 for the 1 Feb 2026 - 31 Jan 2027 leave year}')]
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

        $this->info("Rolling over unused Annual Leave from {$previousLeaveYear}/{$leaveYear} into Carry Forward Leave for {$leaveYear}/".($leaveYear + 1).'...');

        $results = $this->rolloverService->rolloverAll($leaveYear);

        foreach ($results as $userId => $days) {
            $user = User::find($userId);
            $this->line(" - {$user->name}: {$days} day(s) carried forward");
        }

        $this->info(count($results).' user(s) processed.');

        return self::SUCCESS;
    }
}
