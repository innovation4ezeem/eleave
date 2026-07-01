<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN `role` ENUM('admin', 'hod', 'employee') NOT NULL DEFAULT 'employee'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN `role` ENUM('admin', 'employee') NOT NULL DEFAULT 'employee'");
    }
};
