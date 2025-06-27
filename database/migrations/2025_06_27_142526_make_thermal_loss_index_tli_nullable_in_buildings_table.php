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
            $table->integer('thermal_loss_index_tli')->nullable()->change();
            $table->timestamp('last_analyzed_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->integer('thermal_loss_index_tli')->nullable(false)->change();
            $table->timestamp('last_analyzed_at')->nullable(false)->change();
        });
    }
};
