<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Dataset;
use App\Models\Entitlement;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;

class EntitlementSeeder extends Seeder
{
    /**
     * Entitlement Matrix – covers EVERY ABAC branch
     * ----------------------------------------------------
     *  City × Dataset × Entitlement type
     *  – DS-ALL   : Full dataset (buildings)
     *  – DS-AOI   : Polygon-restricted dataset access (buildings)
     *  – DS-BLD   : Hand-picked building GIDs (buildings)
     *  – TILES(A) : Polygon-restricted tile access (thermal_raster)
     *  – TILES(G) : Global tile access (thermal_raster)
     */
    public function run(): void
    {
        Entitlement::truncate();
        $datasets = Dataset::all()->keyBy('name');

        $out = [];

        // Helper closures ---------------------------------------------------
        $squareAOI = function(float $lat, float $lon, float $size) {
            $half = $size/2;
            $sw = new Point($lat-$half, $lon-$half);
            $se = new Point($lat-$half, $lon+$half);
            $ne = new Point($lat+$half, $lon+$half);
            $nw = new Point($lat+$half, $lon-$half);
            return new Polygon([ new LineString([$sw,$se,$ne,$nw,$sw]) ]);
        };

        // ───── Debrecen ───────────────────────────────────────────────────
        $debBld = $datasets['Building Data 2024-Q4 Debrecen']->id;
        $debTil = $datasets['Thermal Raster 2024-Q4 Debrecen']->id;

        // DS-ALL – full building dataset
        $out[] = [ 'type'=>'DS-ALL', 'dataset_id'=>$debBld, 'aoi_geom'=>null, 'building_gids'=>null, 'download_formats'=>['csv','geojson'], 'expires_at'=>now()->addYear() ];

        // DS-AOI – small downtown polygon
        $out[] = [ 'type'=>'DS-AOI', 'dataset_id'=>$debBld, 'aoi_geom'=>$squareAOI(47.5335,21.6295,0.002), 'building_gids'=>null,'download_formats'=>['csv'], 'expires_at'=>now()->addMonths(6) ];

        // DS-BLD – two specific buildings (Low + High TLI)
        $out[] = [ 'type'=>'DS-BLD', 'dataset_id'=>$debBld, 'aoi_geom'=>null, 'building_gids'=>['DEB_001','DEB_004'], 'download_formats'=>['csv'], 'expires_at'=>now()->addMonths(3) ];

        // TILES(A) – AOI-restricted tiles – matches DS-AOI polygon
        $out[] = [ 'type'=>'TILES', 'dataset_id'=>$debTil, 'aoi_geom'=>$squareAOI(47.5335,21.6295,0.01), 'building_gids'=>null,'download_formats'=>null,'expires_at'=>now()->addYear() ];

        // TILES(G) – global full-coverage tiles for demo
        $out[] = [ 'type'=>'TILES', 'dataset_id'=>$debTil, 'aoi_geom'=>null, 'building_gids'=>null,'download_formats'=>null,'expires_at'=>now()->addYear() ];

        // ───── Budapest ──────────────────────────────────────────────────
        $budBld = $datasets['Building Data 2024-Q3 Budapest District V']->id;
        $budTil = $datasets['Thermal Raster 2024-Q3 Budapest District V']->id;

        $out[] = [ 'type'=>'DS-ALL', 'dataset_id'=>$budBld, 'aoi_geom'=>null, 'building_gids'=>null, 'download_formats'=>['csv','geojson'], 'expires_at'=>null ]; // never expires
        $out[] = [ 'type'=>'DS-AOI', 'dataset_id'=>$budBld, 'aoi_geom'=>$squareAOI(47.4980,19.0410,0.002), 'building_gids'=>null, 'download_formats'=>['csv'], 'expires_at'=>now()->addMonths(4) ];
        $out[] = [ 'type'=>'DS-BLD', 'dataset_id'=>$budBld, 'aoi_geom'=>null, 'building_gids'=>['BUD_002','BUD_003'], 'download_formats'=>['csv'], 'expires_at'=>now()->addMonth() ];
        $out[] = [ 'type'=>'TILES', 'dataset_id'=>$budTil, 'aoi_geom'=>null, 'building_gids'=>null, 'download_formats'=>null, 'expires_at'=>now()->addYear() ];

        // ───── Copenhagen – ONLY AOI + TILES(A) to test denial elsewhere ──
        $cphBld = $datasets['Building Data 2023-Q4 Copenhagen']->id;
        $cphTil = $datasets['Thermal Raster 2023-Q4 Copenhagen']->id;

        $out[] = [ 'type'=>'DS-AOI', 'dataset_id'=>$cphBld, 'aoi_geom'=>$squareAOI(55.6765,12.5680,0.003), 'building_gids'=>null, 'download_formats'=>['csv'], 'expires_at'=>now()->addMonths(2) ];
        $out[] = [ 'type'=>'TILES', 'dataset_id'=>$cphTil, 'aoi_geom'=>$squareAOI(55.6765,12.5680,0.02), 'building_gids'=>null, 'download_formats'=>null, 'expires_at'=>now()->addMonths(2) ];

        foreach ($out as $row) { Entitlement::create($row); }

        $this->command->info('🔑 Seeded '.count($out).' entitlement scenarios covering all ABAC branches');
    }
}
