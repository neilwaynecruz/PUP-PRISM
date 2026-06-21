<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        $this->normalizeMovementTypesForCurrentConstraint();
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (movement_type IN ('receive','issue','transfer','condemn','return'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        $this->normalizeMovementTypesForLegacyConstraint();
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (movement_type IN ('receive','issue','sale','adjust','damage','return'))");
    }

    private function normalizeMovementTypesForCurrentConstraint(): void
    {
        $this->replaceMovementTypes([
            'sale' => 'issue',
            'adjust' => 'transfer',
            'damage' => 'condemn',
        ]);
    }

    private function normalizeMovementTypesForLegacyConstraint(): void
    {
        $this->replaceMovementTypes([
            'transfer' => 'adjust',
            'condemn' => 'damage',
        ]);
    }

    /**
     * @param  array<string, string>  $movementTypeMap
     */
    private function replaceMovementTypes(array $movementTypeMap): void
    {
        foreach ($movementTypeMap as $from => $to) {
            DB::table('stock_movements')
                ->where('movement_type', $from)
                ->update(['movement_type' => $to]);
        }
    }
};
