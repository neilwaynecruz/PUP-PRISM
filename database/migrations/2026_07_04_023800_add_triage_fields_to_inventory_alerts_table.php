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
        Schema::table('inventory_alerts', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable()->after('detected_at');
            $table->foreignId('acknowledged_by')->nullable()->after('acknowledged_at')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->after('acknowledged_by')->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable()->after('resolved_by');

            $table->index(['resolved_at', 'acknowledged_at']);
            $table->index(['assigned_to', 'resolved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_alerts', function (Blueprint $table) {
            $table->dropIndex(['resolved_at', 'acknowledged_at']);
            $table->dropIndex(['assigned_to', 'resolved_at']);
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['acknowledged_at', 'resolution_notes']);
        });
    }
};
