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
        Schema::create('user_entitlements', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users'); // Foreign key to the users table
            $table->foreignId('entitlement_id')->constrained('entitlements'); // Foreign key to the entitlements table
            $table->timestamp('created_at'); // Timestamp when the entitlement was granted

            // Composite primary key for uniqueness
            $table->primary(['user_id', 'entitlement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_entitlements');
    }
};
