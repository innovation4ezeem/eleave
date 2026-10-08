<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warning_letters', function (Blueprint $table) {
            // uploaded_by stays the original issuer; updated_by is the last admin who edited
            // the letter or replaced its file.
            $table->foreignId('updated_by')->nullable()->after('uploaded_by')->constrained('users');
        });

        Schema::create('warning_letter_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warning_letter_id')->constrained('warning_letters');
            $table->foreignId('user_id')->constrained('users');
            $table->string('action', 20);
            $table->string('details', 255)->nullable();
            // Nullable on purpose: on MySQL 5.7 a NOT NULL timestamp silently gets
            // DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP. Eloquent sets this value.
            $table->timestamp('created_at')->nullable();

            $table->index(['warning_letter_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warning_letter_events');

        Schema::table('warning_letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
