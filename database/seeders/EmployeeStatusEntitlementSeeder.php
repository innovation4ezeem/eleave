<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EmployeeStatusEntitlementSeeder extends Seeder
{
    /**
     * Seed entitlement tiers for Probation and Intern statuses.
     *
     * Note: Admins should add these manually via /admin/entitlement-tiers UI:
     * - Probation: 24 WFH days, 0 Annual Leave (min_years: 0)
     * - Intern: 24 WFH days, 0 Annual Leave (min_years: 0)
     */
    public function run(): void
    {
        // Intentionally empty - admins will configure via UI
        // to avoid constraint issues with existing data
    }
}
