<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExtractParisSampleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'extract:paris-sample 
                            {--sample-size=50 : Number of buildings to extract}
                            {--center-lat=48.8566 : Central latitude for Paris}
                            {--center-lon=2.3522 : Central longitude for Paris}
                            {--radius=0.02 : Radius in degrees (~2km) around center}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract a sample of buildings from the large Paris CSV file, focusing on central Paris area';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sampleSize = (int) $this->option('sample-size');
        $centerLat = (float) $this->option('center-lat');
        $centerLon = (float) $this->option('center-lon');
        $radius = (float) $this->option('radius');

        $this->info("🚀 Extracting Paris building sample...");
        $this->info("📍 Center: {$centerLat}, {$centerLon}");
        $this->info("📏 Radius: {$radius} degrees (~" . round($radius * 111, 1) . "km)");
        $this->info("📊 Target sample size: {$sampleSize}");

        $inputPath = storage_path('data/paris_buildings.csv');
        $outputPath = storage_path('data/paris_buildings_sample.csv');

        if (!file_exists($inputPath)) {
            $this->error("❌ Input file not found: {$inputPath}");
            return Command::FAILURE;
        }

        try {
            $this->extractSample($inputPath, $outputPath, $sampleSize, $centerLat, $centerLon, $radius);
            
            $this->info("✅ Sample extracted successfully!");
            $this->info("📁 Output file: {$outputPath}");
            
            // Show file size
            $size = filesize($outputPath);
            $this->info("📏 File size: " . $this->formatBytes($size));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Extract sample buildings from CSV
     */
    private function extractSample(string $inputPath, string $outputPath, int $sampleSize, float $centerLat, float $centerLon, float $radius): void
    {
        $inputHandle = fopen($inputPath, 'r');
        $outputHandle = fopen($outputPath, 'w');

        if (!$inputHandle || !$outputHandle) {
            throw new \Exception("Could not open input or output file");
        }

        // Read and write header
        $header = fgetcsv($inputHandle);
        fputcsv($outputHandle, $header);
        
        $extracted = 0;
        $processed = 0;
        $progressInterval = 50000;

        $this->info("🔍 Processing buildings...");

        while (($row = fgetcsv($inputHandle)) !== false && $extracted < $sampleSize) {
            $processed++;
            
            // Show progress
            if ($processed % $progressInterval === 0) {
                $this->info("📈 Processed: {$processed}, Extracted: {$extracted}");
            }

            // Extract coordinates from geometry
            if ($this->isInArea($row[0], $centerLat, $centerLon, $radius)) {
                fputcsv($outputHandle, $row);
                $extracted++;
                
                if ($extracted % 10 === 0) {
                    $this->info("✅ Extracted {$extracted}/{$sampleSize} buildings");
                }
            }
        }

        fclose($inputHandle);
        fclose($outputHandle);

        $this->info("📊 Total processed: {$processed}");
        $this->info("🎯 Total extracted: {$extracted}");
    }

    /**
     * Check if geometry is within the specified area
     */
    private function isInArea(string $geometry, float $centerLat, float $centerLon, float $radius): bool
    {
        // Extract coordinates from MULTIPOLYGON WKT
        // Look for lat/lon pairs in the geometry string
        if (preg_match_all('/(\d+\.\d+)\s+(\d+\.\d+)/', $geometry, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $lon = (float) $match[1];
                $lat = (float) $match[2];
                
                // Check if this coordinate is within our target area
                $latDiff = abs($lat - $centerLat);
                $lonDiff = abs($lon - $centerLon);
                
                if ($latDiff <= $radius && $lonDiff <= $radius) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
} 