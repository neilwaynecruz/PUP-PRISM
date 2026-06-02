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
        // On PostgreSQL the column's CHECK constraint is dropped automatically
        // with the column, but drop it explicitly first to keep this reversible
        // and avoid relying on cascade behaviour.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_stocks DROP CONSTRAINT IF EXISTS product_stocks_reserved_qty_check');
        }

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropColumn('reserved_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->unsignedInteger('reserved_qty')->default(0);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_stocks ADD CONSTRAINT product_stocks_reserved_qty_check CHECK (reserved_qty >= 0)');
        }
    }
};
