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
        Schema::table('buildings', function (Blueprint $table) {
            // Remove the old TLI column
            $table->dropColumn('thermal_loss_index_tli');
            
            // Add new anomaly detection columns
            $table->decimal('average_heatloss', 10, 4)->nullable(); // Average heat loss for the building
            $table->decimal('reference_heatloss', 10, 4)->nullable(); // Reference/baseline heat loss for comparison
            $table->decimal('heatloss_difference', 10, 4)->nullable(); // Difference from reference
            $table->decimal('abs_heatloss_difference', 10, 4)->nullable(); // Absolute difference from reference
            $table->decimal('threshold', 10, 4)->nullable(); // Threshold value for anomaly detection
            $table->boolean('is_anomaly')->default(false); // Boolean flag indicating if building is an anomaly
            $table->decimal('confidence', 5, 4)->nullable(); // Confidence score (0.0 to 1.0)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            // Restore the TLI column
            $table->integer('thermal_loss_index_tli')->nullable();
            
            // Remove anomaly detection columns
            $table->dropColumn([
                'average_heatloss',
                'reference_heatloss', 
                'heatloss_difference',
                'abs_heatloss_difference',
                'threshold',
                'is_anomaly',
                'confidence'
            ]);
        });
    }
};
