<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Backs the new middle "Achievement" panel on the profile page. Note: the
// pre-existing `achievements` table now serves the "Courses" panel (its HR-filled
// data is preserved), so this new panel needs its own table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->date('achieved_on')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_achievements');
    }
};
