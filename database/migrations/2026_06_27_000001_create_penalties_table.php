<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

            $table->date('penalty_date');
            $table->text('mistake');
            $table->enum('penalty_type', ['warning', 'cash']);
            $table->unsignedTinyInteger('penalty_level');
            $table->decimal('penalty_charges', 10, 2)->nullable();

            $table->string('document_path', 500)->nullable();
            $table->string('document_original_name')->nullable();

            $table->string('reported_by');
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('collection_status', ['pending', 'collected'])->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('department_id');
            $table->index(['penalty_type', 'collection_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalties');
    }
};
