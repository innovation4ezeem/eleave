<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_type_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'leave_type_id']);
        });

        // Backfill: every existing user is entitled to every active leave type,
        // preserving the previous "everyone sees all types" behaviour.
        $userIds = DB::table('users')->pluck('id');
        $leaveTypeIds = DB::table('leave_types')->where('is_active', true)->pluck('id');
        $now = now();

        $rows = [];
        foreach ($userIds as $userId) {
            foreach ($leaveTypeIds as $leaveTypeId) {
                $rows[] = [
                    'user_id' => $userId,
                    'leave_type_id' => $leaveTypeId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('leave_type_user')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_type_user');
    }
};
