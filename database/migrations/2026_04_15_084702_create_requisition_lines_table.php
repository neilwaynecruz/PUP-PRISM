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
        Schema::create('requisition_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('qty_requested')->default(1);
            $table->unsignedInteger('qty_issued')->default(0);
            $table->timestamps();

            $table->unique(['requisition_id', 'product_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE requisition_lines ADD CONSTRAINT requisition_lines_qty_requested_check CHECK (qty_requested > 0)');
            DB::statement('ALTER TABLE requisition_lines ADD CONSTRAINT requisition_lines_qty_issued_check CHECK (qty_issued >= 0)');
            DB::statement('ALTER TABLE requisition_lines ADD CONSTRAINT requisition_lines_qty_issued_le_requested_check CHECK (qty_issued <= qty_requested)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisition_lines');
    }
};
