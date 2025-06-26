<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalize existing data_type values to snake_case
        DB::table('datasets')->where('data_type', 'thermal-raster')->update(['data_type' => 'thermal_raster']);
        DB::table('datasets')->where('data_type', 'building-data')->update(['data_type' => 'building_data']);

        // If there are other legacy values, add additional transformations here
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert changes (optional)
        DB::table('datasets')->where('data_type', 'thermal_raster')->update(['data_type' => 'thermal-raster']);
        DB::table('datasets')->where('data_type', 'building_data')->update(['data_type' => 'building-data']);
    }
}; 