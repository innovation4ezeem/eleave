<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE leave_applications MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'cancelled', 'change_requested') NOT NULL DEFAULT 'pending'");

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->string('prev_status', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('prev_status');
        });

        DB::statement("ALTER TABLE leave_applications MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending'");
    }
};
