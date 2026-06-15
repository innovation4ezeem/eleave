<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'employee'])->default('employee')->after('email');
            $table->foreignId('department_id')->nullable()->after('role')
                ->constrained('departments')->nullOnDelete();
            $table->string('employee_no', 50)->nullable()->after('department_id');
            $table->date('joined_at')->nullable()->after('employee_no');
            $table->boolean('is_active')->default(true)->after('joined_at');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['role', 'employee_no', 'joined_at', 'is_active']);
            $table->dropSoftDeletes();
        });
    }
};
