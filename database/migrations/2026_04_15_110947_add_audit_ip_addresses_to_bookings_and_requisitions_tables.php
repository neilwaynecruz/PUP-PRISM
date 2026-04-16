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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('requested_ip_address', 45)->nullable();
            $table->string('approved_ip_address', 45)->nullable();
        });

        Schema::table('requisitions', function (Blueprint $table) {
            $table->string('requested_ip_address', 45)->nullable();
            $table->string('approved_ip_address', 45)->nullable();
            $table->string('issued_ip_address', 45)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn(['requested_ip_address', 'approved_ip_address', 'issued_ip_address']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['requested_ip_address', 'approved_ip_address']);
        });
    }
};
