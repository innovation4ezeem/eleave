<?php

namespace Tests;

use App\Models\LeaveYearLock;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // LeaveYearLock::isLocked() caches per PHP process. RefreshDatabase rolls
        // rows back without model events, so clear it between tests.
        LeaveYearLock::flushCache();
    }
}
