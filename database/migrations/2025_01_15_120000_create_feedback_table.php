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
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('type')->default('general'); // 'general', 'bug_report', 'feature_request'
            $table->string('category')->nullable(); // 'ui', 'performance', 'data', 'map', 'authentication', 'other'
            $table->string('subject');
            $table->text('description');
            $table->string('priority')->default('medium'); // 'low', 'medium', 'high', 'critical'
            $table->string('contact_email')->nullable(); // For anonymous feedback
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};