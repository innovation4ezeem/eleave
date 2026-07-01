<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->string('change_action', 20)->nullable()->after('prev_status');
            $table->foreignId('requested_leave_type_id')->nullable()->after('change_action')->constrained('leave_types')->nullOnDelete();
            $table->date('requested_start_date')->nullable()->after('requested_leave_type_id');
            $table->date('requested_end_date')->nullable()->after('requested_start_date');
            $table->enum('requested_start_half', ['full', 'am', 'pm'])->nullable()->after('requested_end_date');
            $table->enum('requested_end_half', ['full', 'am', 'pm'])->nullable()->after('requested_start_half');
            $table->decimal('requested_total_days', 5, 1)->nullable()->after('requested_end_half');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_leave_type_id');
            $table->dropColumn([
                'change_action',
                'requested_start_date',
                'requested_end_date',
                'requested_start_half',
                'requested_end_half',
                'requested_total_days',
            ]);
        });
    }
};
