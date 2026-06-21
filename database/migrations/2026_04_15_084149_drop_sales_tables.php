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
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS sale_lines CASCADE');
            DB::statement('DROP TABLE IF EXISTS sales CASCADE');

            return;
        }

        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('sold_at')->useCurrent();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cashier_id', 'sold_at']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sale_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sale_lines ADD CONSTRAINT sale_lines_qty_check CHECK (qty > 0)');
        }

        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'sale_id')) {
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_sale_id_foreign');
                DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE SET NULL');

                return;
            }

            Schema::table('stock_movements', function (Blueprint $table) {
                $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
            });
        }
    }
};
