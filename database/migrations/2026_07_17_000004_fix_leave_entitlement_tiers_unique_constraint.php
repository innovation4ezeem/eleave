<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old constraint and add new one that includes employee_status
        DB::statement('ALTER TABLE leave_entitlement_tiers DROP INDEX leave_entitlement_tiers_leave_type_id_min_years_unique, ADD UNIQUE KEY leave_entitlement_tiers_leave_type_id_min_years_status_unique (leave_type_id, min_years, employee_status)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE leave_entitlement_tiers DROP INDEX leave_entitlement_tiers_leave_type_id_min_years_status_unique, ADD UNIQUE KEY leave_entitlement_tiers_leave_type_id_min_years_unique (leave_type_id, min_years)');
    }
};
