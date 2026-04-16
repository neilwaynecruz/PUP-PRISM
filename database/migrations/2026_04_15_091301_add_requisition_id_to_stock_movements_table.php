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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('requisition_id')
                ->nullable()
                ->after('asset_id')
                ->constrained('requisitions')
                ->nullOnDelete();

            $table->index(['requisition_id', 'performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['requisition_id', 'performed_at']);
            $table->dropConstrainedForeignId('requisition_id');
        });
    }
};
