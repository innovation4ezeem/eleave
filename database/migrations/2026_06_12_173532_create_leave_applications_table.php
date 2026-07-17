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
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->enum('start_half', ['full', 'am', 'pm'])->default('full');
            $table->enum('end_half', ['full', 'am', 'pm'])->default('full');
            $table->decimal('total_days', 5, 1);

            $table->text('reason');
            $table->string('document_path', 500)->nullable();
            $table->string('document_original_name')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('applied_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('remarks')->nullable();

            $table->boolean('is_historical')->default(false);
            $table->smallInteger('leave_year');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
