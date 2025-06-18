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
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Human-readable name (e.g., "Thermal Raster v2024-Q4 Debrecen")
            $table->text('description')->nullable(); // Detailed description of the dataset
            $table->string('data_type', 50); // Type of data bundle (e.g., 'thermal-raster', 'building-data')
            $table->text('storage_location'); // Path or prefix in object storage (e.g., S3 bucket/prefix)
            $table->string('version', 50)->nullable(); // Version identifier for the dataset
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datasets');
    }
};
