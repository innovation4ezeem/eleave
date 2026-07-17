<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The company leave year runs 1 Jan - 31 Dec, matching the calendar year.
 * The "leave year" value is simply that calendar year, e.g. leave year 2026
 * covers 1 Jan 2026 - 31 Dec 2026.
 *
 * Historical leave_year values created before this rule changed (when the
 * leave year ran 1 Feb - 31 Jan) are left as-is in the database and are not
 * backfilled; only newly derived leave years use this Jan-Dec rule.
 */
class LeaveYear
{
    public static function forDate(CarbonInterface|string $date): int
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return $date->year;
    }

    public static function current(): int
    {
        return self::forDate(Carbon::now());
    }

    public static function startDate(int $leaveYear): Carbon
    {
        return Carbon::create($leaveYear, 1, 1)->startOfDay();
    }

    public static function endDate(int $leaveYear): Carbon
    {
        return Carbon::create($leaveYear, 12, 31)->endOfDay();
    }
}
