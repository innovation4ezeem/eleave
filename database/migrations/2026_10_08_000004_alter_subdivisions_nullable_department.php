<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subdivisions', function (Blueprint $table) {
            // Drop old unique + FK
            $table->dropUnique('subdivisions_department_id_name_unique');
            $table->dropForeign('subdivisions_department_id_foreign');

            // Re-add department_id as nullable with nullOnDelete
            $table->foreignId('department_id')
                ->nullable()
                ->change();
            $table->foreign('department_id')
                ->references('id')->on('departments')
                ->nullOnDelete();

            // New unique: just on name
            $table->unique('name', 'subdivisions_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('subdivisions', function (Blueprint $table) {
            $table->dropUnique('subdivisions_name_unique');
            $table->dropForeign('subdivisions_department_id_foreign');

            $table->foreignId('department_id')->change();
            $table->foreign('department_id')
                ->references('id')->on('departments')
                ->cascadeOnDelete();

            $table->unique(['department_id', 'name'], 'subdivisions_department_id_name_unique');
        });
    }
};
