<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The company leave year runs 1 Feb - 31 Jan. The "leave year" value is the
 * starting calendar year of that period, e.g. leave year 2026 covers
 * 1 Feb 2026 - 31 Jan 2027.
 */
class LeaveYear
{
    public static function forDate(CarbonInterface|string $date): int
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return $date->month === 1 ? $date->year - 1 : $date->year;
    }

    public static function current(): int
    {
        return self::forDate(Carbon::now());
    }

    public static function startDate(int $leaveYear): Carbon
    {
        return Carbon::create($leaveYear, 2, 1)->startOfDay();
    }

    public static function endDate(int $leaveYear): Carbon
    {
        return Carbon::create($leaveYear + 1, 1, 31)->endOfDay();
    }
}
