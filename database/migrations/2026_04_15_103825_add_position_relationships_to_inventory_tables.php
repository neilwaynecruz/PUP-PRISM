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
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
        });

        Schema::table('handover_logs', function (Blueprint $table) {
            $table->foreignId('from_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('to_position_id')->nullable()->constrained('positions')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('requester_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('approver_position_id')->nullable()->constrained('positions')->nullOnDelete();
        });

        Schema::table('requisitions', function (Blueprint $table) {
            $table->foreignId('requester_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('approver_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('issued_position_id')->nullable()->constrained('positions')->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('accountable_position_id')->nullable()->constrained('positions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accountable_position_id');
        });

        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_position_id');
            $table->dropConstrainedForeignId('approver_position_id');
            $table->dropConstrainedForeignId('requester_position_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_position_id');
            $table->dropConstrainedForeignId('requester_position_id');
        });

        Schema::table('handover_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('to_position_id');
            $table->dropConstrainedForeignId('from_position_id');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
        });
    }
};
