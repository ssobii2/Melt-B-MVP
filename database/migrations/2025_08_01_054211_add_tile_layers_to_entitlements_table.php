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
        Schema::table('entitlements', function (Blueprint $table) {
            $table->json('tile_layers')->nullable()->after('building_gids'); // JSON array of tile layer names for TILES entitlements
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entitlements', function (Blueprint $table) {
            $table->dropColumn('tile_layers');
        });
    }
};
