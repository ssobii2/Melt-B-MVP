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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); // Unique identifier
            $table->foreignId('user_id')->constrained('users'); // User who performed the action
            $table->string('action'); // Description of the action (e.g., 'user_created', 'entitlement_updated')
            $table->string('target_type', 100)->nullable(); // Type of entity affected (e.g., 'user', 'entitlement')
            $table->unsignedBigInteger('target_id')->nullable(); // ID of the affected entity
            $table->json('old_values')->nullable(); // Snapshot of relevant data before the action
            $table->json('new_values')->nullable(); // Snapshot of relevant data after the action
            $table->string('ip_address', 45)->nullable(); // IP address of the user
            $table->text('user_agent')->nullable(); // User agent string of the client
            $table->timestamp('created_at'); // Timestamp of the action
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
