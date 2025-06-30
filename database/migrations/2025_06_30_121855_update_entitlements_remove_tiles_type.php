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
        // First, remove user_entitlements records that reference TILES entitlements
        $tilesEntitlementIds = DB::table('entitlements')
            ->where('type', 'TILES')
            ->pluck('id');
        
        if ($tilesEntitlementIds->isNotEmpty()) {
            DB::table('user_entitlements')
                ->whereIn('entitlement_id', $tilesEntitlementIds)
                ->delete();
        }
        
        // Now remove the TILES entitlements themselves
        DB::table('entitlements')->where('type', 'TILES')->delete();
        
        // Note: We're not changing the schema since other entitlement types
        // (DS-ALL, DS-AOI, DS-BLD) are still valid for the new system
        // The application logic will handle the removal of TILES functionality
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We cannot restore deleted TILES entitlements as we don't have
        // the original data. This migration is largely irreversible.
        // However, the schema remains intact so TILES could be re-added if needed.
    }
};
