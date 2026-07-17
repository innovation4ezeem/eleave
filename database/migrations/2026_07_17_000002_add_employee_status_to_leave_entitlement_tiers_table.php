<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_entitlement_tiers', function (Blueprint $table) {
            $table->enum('employee_status', ['probation', 'intern', 'permanent'])
                ->nullable()
                ->after('min_years')
                ->comment('If null, applies to all statuses. If set, only applies to this status.');
        });
    }

    public function down(): void
    {
        Schema::table('leave_entitlement_tiers', function (Blueprint $table) {
            $table->dropColumn('employee_status');
        });
    }
};
