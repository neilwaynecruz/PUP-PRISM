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
            $table->timestamp('cancelled_at')->nullable()->after('verified_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_by');
            $table->timestamp('last_sent_at')->nullable()->after('initiated_at');

            $table->index(['asset_id', 'verified_at', 'cancelled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('handover_logs', function (Blueprint $table) {
            $table->dropIndex(['asset_id', 'verified_at', 'cancelled_at']);
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason', 'last_sent_at']);
        });
    }
};
