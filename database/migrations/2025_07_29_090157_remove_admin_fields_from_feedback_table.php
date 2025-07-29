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
        Schema::table('feedback', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['type', 'status']);
            $table->dropIndex(['status']);
            
            // Drop foreign key constraint
            $table->dropForeign(['resolved_by']);
            
            // Drop admin-related columns
            $table->dropColumn(['status', 'admin_notes', 'resolved_at', 'resolved_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            // Re-add admin-related columns
            $table->string('status')->default('open');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Re-add indexes
            $table->index(['type', 'status']);
            $table->index('status');
        });
    }
};
