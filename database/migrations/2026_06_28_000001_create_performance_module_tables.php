<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competence_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('competence_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('votee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('competence_categories')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['votee_id', 'category_id']);
            $table->index('voter_id');
            $table->index('created_at');
        });

        Schema::create('performance_inputs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label');
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('performance_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('expression', 500);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('performance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->json('values');
            $table->json('scores');
            $table->decimal('total_score', 8, 2)->default(0);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
        });

        // Seed the default performance inputs
        $now = now();
        $inputs = [
            ['key' => 'achieved', 'label' => 'Achieved', 'sort_order' => 1],
            ['key' => 'objective', 'label' => 'Objective', 'sort_order' => 2],
            ['key' => 'completed', 'label' => 'Completed Tasks', 'sort_order' => 3],
            ['key' => 'total_tasks', 'label' => 'Total Tasks', 'sort_order' => 4],
            ['key' => 'error', 'label' => 'Errors', 'sort_order' => 5],
        ];
        foreach ($inputs as &$i) {
            $i['created_at'] = $now;
            $i['updated_at'] = $now;
        }
        DB::table('performance_inputs')->insert($inputs);

        // Seed the default performance components (the formula currently in use)
        $components = [
            ['name' => 'KPI (50%)', 'expression' => '(achieved / objective) * 50', 'sort_order' => 1],
            ['name' => 'Tasks Based (25%)', 'expression' => '(completed / total_tasks) * 25', 'sort_order' => 2],
            ['name' => 'Quality (25%)', 'expression' => '((completed - error) / 100) * 25', 'sort_order' => 3],
        ];
        foreach ($components as &$c) {
            $c['is_active'] = true;
            $c['created_at'] = $now;
            $c['updated_at'] = $now;
        }
        DB::table('performance_components')->insert($components);
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_entries');
        Schema::dropIfExists('performance_components');
        Schema::dropIfExists('performance_inputs');
        Schema::dropIfExists('competence_votes');
        Schema::dropIfExists('competence_categories');
    }
};
