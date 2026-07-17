<?php

namespace App\Console\Commands;

use App\Models\LeaveBalance;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('leave:expire-carry-forward {leave_year : The leave year whose Carry Forward Leave should expire, e.g. 2026 for the 1 Jan 2026 - 31 Dec 2026 leave year}')]
#[Description('Expire any remaining Carry Forward Leave for the given leave year (runs 1 March; usable in January and February only). Zeroes the remaining balance via an audit adjustment; used days are preserved.')]
class ExpireCarryForwardCommand extends Command
{
    public function handle(): int
    {
        $leaveYear = (int) $this->argument('leave_year');

        $carryForwardType = LeaveType::where('code', 'carry_forward')->first();

        if (! $carryForwardType) {
            $this->error('Carry Forward Leave type not found.');

            return self::FAILURE;
        }

        $balances = LeaveBalance::where('leave_type_id', $carryForwardType->id)
            ->where('leave_year', $leaveYear)
            ->get();

        $expiredCount = 0;

        foreach ($balances as $balance) {
            $remaining = (float) $balance->remaining_days;

            if ($remaining <= 0) {
                continue;
            }

            DB::transaction(function () use ($balance, $remaining, &$expiredCount) {
                $oldAdjustment = (float) $balance->adjustment_days;
                $newAdjustment = $oldAdjustment - $remaining;

                LeaveBalanceAdjustment::create([
                    'leave_balance_id' => $balance->id,
                    'changed_by' => null,
                    'field_changed' => 'adjustment_days',
                    'old_value' => $oldAdjustment,
                    'new_value' => $newAdjustment,
                    'reason' => 'Carry Forward Leave expired on 1 March',
                ]);

                $balance->update(['adjustment_days' => $newAdjustment]);
                $expiredCount++;
            });
        }

        $this->info("Expired Carry Forward Leave for {$expiredCount} balance(s) in leave year {$leaveYear}.");

        return self::SUCCESS;
    }
}
