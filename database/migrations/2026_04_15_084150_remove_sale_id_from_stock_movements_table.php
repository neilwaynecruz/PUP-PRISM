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
        if (! Schema::hasColumn('stock_movements', 'sale_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_sale_id_foreign');
            DB::statement('DROP INDEX IF EXISTS stock_movements_sale_id_performed_at_index');
            DB::statement('ALTER TABLE stock_movements DROP COLUMN IF EXISTS sale_id');

            return;
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['sale_id', 'performed_at']);
            $table->dropConstrainedForeignId('sale_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasTable('sales')) {
                $table->foreignId('sale_id')->nullable()->after('asset_id')->constrained('sales')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('sale_id')->nullable()->after('asset_id');
            }

            $table->index(['sale_id', 'performed_at']);
        });
    }
};
