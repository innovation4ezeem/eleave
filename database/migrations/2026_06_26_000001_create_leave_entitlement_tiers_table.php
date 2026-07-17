<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlement_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('min_years');
            $table->decimal('days', 5, 1);
            $table->timestamps();

            $table->unique(['leave_type_id', 'min_years']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entitlement_tiers');
    }
};
