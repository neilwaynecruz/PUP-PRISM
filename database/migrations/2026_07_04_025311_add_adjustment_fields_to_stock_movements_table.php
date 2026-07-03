<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('reason_code')->nullable()->after('movement_type');
            $table->integer('counted_qty')->nullable()->after('qty_after');
            $table->integer('variance_qty')->nullable()->after('counted_qty');
            $table->index(['movement_type', 'reason_code'], 'stock_movements_type_reason_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (movement_type IN ('receive','issue','transfer','condemn','return','adjustment','cycle_count'))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (movement_type IN ('receive','issue','transfer','condemn','return'))");
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_type_reason_idx');
            $table->dropColumn(['reason_code', 'counted_qty', 'variance_qty']);
        });
    }
};
