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
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // The type of entitlement (e.g., 'DS-ALL', 'DS-AOI', 'DS-BLD', 'TILES')
            $table->foreignId('dataset_id')->constrained('datasets'); // Which dataset this entitlement applies to
            $table->geometry('aoi_geom', 'polygon', 4326)->nullable(); // PostGIS Polygon for Area of Interest (SRID 4326 for WGS84)
            $table->json('building_gids')->nullable(); // JSON array of specific building GIDs (for 'DS-BLD')
            $table->json('download_formats')->nullable(); // Allowed download formats (e.g., ["csv", "geojson", "xlsx"])
            $table->timestamp('expires_at')->nullable(); // Date/time when the entitlement expires
            $table->timestamps();

            // Add spatial index for the geometry column
            $table->spatialIndex('aoi_geom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
