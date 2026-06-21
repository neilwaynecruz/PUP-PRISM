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
        Schema::create('forecast_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('method')->default('exponential_smoothing');
            $table->unsignedSmallInteger('lookback_days')->default(90);
            $table->unsignedSmallInteger('forecast_horizon_days')->default(30);
            $table->unsignedSmallInteger('lead_time_days')->default(14);
            $table->unsignedSmallInteger('safety_stock_days')->default(7);
            $table->decimal('smoothing_factor', 4, 2)->nullable();
            $table->decimal('trend_factor', 4, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('product_id');
            $table->index(['is_active', 'method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_profiles');
    }
};
