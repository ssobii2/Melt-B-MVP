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
        Schema::table('audit_logs', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['user_id']);

            // Modify the column to be nullable
            $table->foreignId('user_id')->nullable()->change();

            // Add the foreign key constraint back with nullable
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['user_id']);

            // Modify the column back to not nullable
            $table->foreignId('user_id')->change();

            // Add the foreign key constraint back
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
