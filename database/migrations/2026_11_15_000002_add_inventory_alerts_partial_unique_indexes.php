<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        // Resolve any historical duplicate unresolved rows before creating partial unique indexes.
        $duplicateIds = DB::table('inventory_alerts as older')
            ->join('inventory_alerts as newer', function ($join): void {
                $join->on('older.type', '=', 'newer.type')
                    ->whereColumn('older.id', '<', 'newer.id')
                    ->whereNull('older.resolved_at')
                    ->whereNull('newer.resolved_at')
                    ->whereRaw('COALESCE(older.product_id, 0) = COALESCE(newer.product_id, 0)')
                    ->whereRaw('COALESCE(older.stock_lot_id, 0) = COALESCE(newer.stock_lot_id, 0)');
            })
            ->pluck('older.id')
            ->unique()
            ->all();

        if ($duplicateIds !== []) {
            DB::table('inventory_alerts')
                ->whereIn('id', $duplicateIds)
                ->update(['resolved_at' => now()]);
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS inventory_alerts_unresolved_product_unique '
            .'ON inventory_alerts (type, product_id) '
            .'WHERE resolved_at IS NULL AND stock_lot_id IS NULL',
        );

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS inventory_alerts_unresolved_lot_unique '
            .'ON inventory_alerts (type, stock_lot_id) '
            .'WHERE resolved_at IS NULL AND stock_lot_id IS NOT NULL',
        );
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS inventory_alerts_unresolved_product_unique');
        DB::statement('DROP INDEX IF EXISTS inventory_alerts_unresolved_lot_unique');
    }
};
