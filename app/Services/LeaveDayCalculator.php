<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class LeaveDayCalculator
{
    /**
     * Calculate the number of working days (Mon-Fri) between two dates,
     * applying half-day deductions on the start/end date.
     *
     * For single-day applications, $endHalf is ignored (treated as 'full')
     * since only $startHalf determines the day's value.
     */
    public function calculate(
        CarbonImmutable|string $start,
        CarbonImmutable|string $end,
        string $startHalf = 'full',
        string $endHalf = 'full',
    ): float {
        $start = $start instanceof CarbonImmutable ? $start->startOfDay() : CarbonImmutable::parse($start)->startOfDay();
        $end = $end instanceof CarbonImmutable ? $end->startOfDay() : CarbonImmutable::parse($end)->startOfDay();

        if ($end->lt($start)) {
            return 0.0;
        }

        $isSingleDay = $start->equalTo($end);
        if ($isSingleDay) {
            $endHalf = 'full';
        }

        $total = 0.0;
        $cursor = $start;

        while ($cursor->lte($end)) {
            if (! $cursor->isWeekend()) {
                $dayValue = 1.0;

                if ($cursor->equalTo($start) && $startHalf !== 'full') {
                    $dayValue = 0.5;
                } elseif ($cursor->equalTo($end) && $endHalf !== 'full') {
                    $dayValue = 0.5;
                }

                $total += $dayValue;
            }

            $cursor = $cursor->addDay();
        }

        return $total;
    }
}
