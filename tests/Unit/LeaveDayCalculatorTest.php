<?php

namespace Tests\Unit;

use App\Services\LeaveDayCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LeaveDayCalculatorTest extends TestCase
{
    private LeaveDayCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LeaveDayCalculator();
    }

    public function test_full_single_day(): void
    {
        // Monday
        $days = $this->calculator->calculate('2026-02-02', '2026-02-02');
        $this->assertSame(1.0, $days);
    }

    public function test_half_day_single_day_am(): void
    {
        $days = $this->calculator->calculate('2026-02-02', '2026-02-02', 'am');
        $this->assertSame(0.5, $days);
    }

    public function test_end_half_ignored_for_single_day(): void
    {
        // end_half should be forced to 'full' and not double-count/zero-out
        $days = $this->calculator->calculate('2026-02-02', '2026-02-02', 'full', 'pm');
        $this->assertSame(1.0, $days);
    }

    public function test_simple_week_range_excludes_weekend(): void
    {
        // Mon 2026-02-02 to Fri 2026-02-06 = 5 working days
        $days = $this->calculator->calculate('2026-02-02', '2026-02-06');
        $this->assertSame(5.0, $days);
    }

    public function test_range_spanning_weekend(): void
    {
        // Fri 2026-02-06 to Mon 2026-02-09 = Fri + Mon = 2 working days
        $days = $this->calculator->calculate('2026-02-06', '2026-02-09');
        $this->assertSame(2.0, $days);
    }

    public function test_half_day_at_start_of_range(): void
    {
        // Mon (am half) to Wed = 0.5 + 1 + 1 = 2.5
        $days = $this->calculator->calculate('2026-02-02', '2026-02-04', 'am');
        $this->assertSame(2.5, $days);
    }

    public function test_half_day_at_end_of_range(): void
    {
        // Mon to Wed (pm half) = 1 + 1 + 0.5 = 2.5
        $days = $this->calculator->calculate('2026-02-02', '2026-02-04', 'full', 'pm');
        $this->assertSame(2.5, $days);
    }

    public function test_half_day_at_both_start_and_end(): void
    {
        // Mon (am) to Fri (pm) = 0.5 + 1 + 1 + 1 + 0.5 = 4.0
        $days = $this->calculator->calculate('2026-02-02', '2026-02-06', 'am', 'pm');
        $this->assertSame(4.0, $days);
    }

    public function test_weekend_only_range_returns_zero(): void
    {
        // Sat 2026-02-07 to Sun 2026-02-08
        $days = $this->calculator->calculate('2026-02-07', '2026-02-08');
        $this->assertSame(0.0, $days);
    }

    public function test_end_date_before_start_date_returns_zero(): void
    {
        $days = $this->calculator->calculate('2026-02-06', '2026-02-02');
        $this->assertSame(0.0, $days);
    }

    public function test_accepts_carbon_immutable_instances(): void
    {
        $start = CarbonImmutable::create(2026, 2, 2);
        $end = CarbonImmutable::create(2026, 2, 6);

        $days = $this->calculator->calculate($start, $end);
        $this->assertSame(5.0, $days);
    }
}
