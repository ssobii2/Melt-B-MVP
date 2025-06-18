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
        Schema::create('buildings', function (Blueprint $table) {
            $table->string('gid')->primary(); // Global Identifier for the building (from source data)
            $table->geometry('geometry', 'polygon', 4326); // PostGIS Polygon representing the building's footprint (SRID 4326 for WGS84)
            $table->integer('thermal_loss_index_tli'); // Calculated Thermal Loss Index (0-100)
            $table->string('building_type_classification', 100); // e.g., 'residential', 'commercial', 'industrial'
            $table->decimal('co2_savings_estimate', 10, 2)->nullable(); // Estimated CO2 savings potential
            $table->text('address')->nullable(); // Building's street address
            $table->text('owner_operator_details')->nullable(); // Business contact details for owner/operator (GDPR compliant)
            $table->string('cadastral_reference')->nullable(); // Cadastral reference ID
            $table->foreignId('dataset_id')->constrained('datasets'); // Which dataset provided this building's thermal data
            $table->timestamp('last_analyzed_at'); // Timestamp of the thermal analysis
            $table->integer('before_renovation_tli')->nullable(); // TLI before any renovation (for comparison)
            $table->integer('after_renovation_tli')->nullable(); // TLI after renovation (for comparison)
            $table->timestamps();

            // Add spatial index for the geometry column
            $table->spatialIndex('geometry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
