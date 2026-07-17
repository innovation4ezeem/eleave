<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the FK, relax the column to nullable, then re-add the FK so the
        // 1-March system expiry can record an adjustment with changed_by = NULL.
        DB::statement('ALTER TABLE leave_balance_adjustments DROP FOREIGN KEY leave_balance_adjustments_changed_by_foreign');
        DB::statement('ALTER TABLE leave_balance_adjustments MODIFY COLUMN changed_by BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE leave_balance_adjustments ADD CONSTRAINT leave_balance_adjustments_changed_by_foreign FOREIGN KEY (changed_by) REFERENCES users(id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE leave_balance_adjustments DROP FOREIGN KEY leave_balance_adjustments_changed_by_foreign');
        DB::statement('ALTER TABLE leave_balance_adjustments MODIFY COLUMN changed_by BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE leave_balance_adjustments ADD CONSTRAINT leave_balance_adjustments_changed_by_foreign FOREIGN KEY (changed_by) REFERENCES users(id)');
    }
};
