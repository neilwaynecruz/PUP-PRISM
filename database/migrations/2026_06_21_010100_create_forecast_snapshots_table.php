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
        Schema::create('forecast_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date');
            $table->string('forecast_method');
            $table->unsignedInteger('current_on_hand_qty')->default(0);
            $table->unsignedInteger('reorder_point_qty')->default(0);
            $table->decimal('predicted_daily_consumption', 10, 2);
            $table->unsignedInteger('predicted_days_until_stockout')->nullable();
            $table->date('predicted_stockout_date')->nullable();
            $table->unsignedInteger('recommended_reorder_qty')->default(0);
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['product_id', 'forecast_date']);
            $table->index(['forecast_date', 'predicted_days_until_stockout'], 'forecast_snapshots_date_stockout_index');
            $table->index(['forecast_date', 'recommended_reorder_qty'], 'forecast_snapshots_date_reorder_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_snapshots');
    }
};
