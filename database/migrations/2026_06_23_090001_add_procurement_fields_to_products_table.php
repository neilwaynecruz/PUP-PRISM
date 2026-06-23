<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('origin_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('lead_time_days')->nullable()->after('reorder_threshold');
            $table->decimal('unit_price', 12, 2)->nullable()->after('lead_time_days');

            $table->index(['supplier_id', 'is_active'], 'products_supplier_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_supplier_active_index');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['lead_time_days', 'unit_price']);
        });
    }
};
