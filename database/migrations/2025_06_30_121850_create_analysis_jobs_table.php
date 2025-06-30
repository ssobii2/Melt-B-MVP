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
        Schema::create('analysis_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 50)->default('pending'); // pending, running, completed, failed
            $table->json('input_source_links')->nullable(); // Generic input links (S3, URLs, etc.)
            $table->text('output_csv_url')->nullable(); // URL to the completed CSV file
            $table->text('external_job_id')->nullable(); // ID from the external analysis system
            $table->json('metadata')->nullable(); // Additional metadata about the job
            $table->timestamp('started_at')->nullable(); // When the external job started
            $table->timestamp('completed_at')->nullable(); // When the external job completed
            $table->text('error_message')->nullable(); // Error details if job failed
            $table->timestamps();
            
            // Add index for status queries
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_jobs');
    }
};
