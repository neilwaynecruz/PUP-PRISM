<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('reference_no')->nullable();
            $table->timestamp('received_at');
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('qty_received');
            $table->unsignedInteger('qty_remaining');
            $table->timestamps();

            $table->index(['product_id', 'received_at']);
            $table->index('expires_at');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_lots ADD CONSTRAINT stock_lots_qty_received_check CHECK (qty_received >= 0)');
            DB::statement('ALTER TABLE stock_lots ADD CONSTRAINT stock_lots_qty_remaining_check CHECK (qty_remaining >= 0)');
            DB::statement('ALTER TABLE stock_lots ADD CONSTRAINT stock_lots_qty_remaining_le_received_check CHECK (qty_remaining <= qty_received)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
