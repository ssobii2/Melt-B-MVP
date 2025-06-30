<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing thermal_raster datasets to use new naming
        DB::table('datasets')
            ->whereIn('data_type', ['thermal_raster', 'thermal_rasters'])
            ->update(['data_type' => 'building_anomalies']);
            
        // Note: The data_type column already exists and supports the new value
        // We're just updating existing records and establishing the new type name
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original thermal_raster naming
        DB::table('datasets')
            ->where('data_type', 'building_anomalies')
            ->update(['data_type' => 'thermal_raster']);
    }
};
