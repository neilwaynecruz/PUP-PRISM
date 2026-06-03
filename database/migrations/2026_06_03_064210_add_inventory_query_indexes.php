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
        $this->addIndexIfMissing('stock_movements', 'stock_movements_movement_type_index', ['movement_type']);
        $this->addIndexIfMissing('stock_movements', 'stock_movements_performed_by_index', ['performed_by']);
        $this->addIndexIfMissing('products', 'products_type_index', ['type']);
        $this->addIndexIfMissing('products', 'products_is_active_type_index', ['is_active', 'type']);

        // `products.sku` is already backed by the existing unique index
        // (`products_sku_unique`), so adding another plain index would be redundant.
        if (! $this->hasIndexForColumns('products', ['sku'])) {
            $this->addIndexIfMissing('products', 'products_sku_index', ['sku']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('stock_movements', 'stock_movements_movement_type_index');
        $this->dropIndexIfExists('stock_movements', 'stock_movements_performed_by_index');
        $this->dropIndexIfExists('products', 'products_type_index');
        $this->dropIndexIfExists('products', 'products_is_active_type_index');
        $this->dropIndexIfExists('products', 'products_sku_index');
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

    /**
     * @param  array<int, string>  $columns
     */
    private function hasIndexForColumns(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'] ?? null) === $columns) {
                return true;
            }
        }

        return false;
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
