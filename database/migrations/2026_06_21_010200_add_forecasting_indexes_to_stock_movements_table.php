<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing(
            'stock_movements',
            'stock_movements_product_type_performed_at_index',
            ['product_id', 'movement_type', 'performed_at'],
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('stock_movements', 'stock_movements_product_type_performed_at_index');
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if ($this->hasIndexNamed($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function hasIndexNamed(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->hasIndexNamed($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }
};
