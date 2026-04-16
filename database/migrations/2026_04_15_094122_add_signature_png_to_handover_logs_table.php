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
        Schema::table('handover_logs', function (Blueprint $table) {
            $table->longText('signature_png')->nullable()->after('verified_ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('handover_logs', function (Blueprint $table) {
            $table->dropColumn('signature_png');
        });
    }
};
