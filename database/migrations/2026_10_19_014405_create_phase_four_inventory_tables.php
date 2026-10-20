<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('building')->nullable();
            $table->string('room')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department_id', 'is_active']);
            $table->index(['building', 'room']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('current_location_id')->nullable()->after('position_id')->constrained('locations')->nullOnDelete();
            $table->string('lifecycle_status')->default('active')->after('status');
            $table->string('condition_status')->default('serviceable')->after('lifecycle_status');
            $table->index(['current_location_id', 'lifecycle_status']);
            $table->index('condition_status');
        });

        Schema::create('inventory_count_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('counting_date');
            $table->string('scope');
            $table->string('status')->default('draft');
            $table->timestamp('snapshot_at');
            $table->unsignedBigInteger('movement_cutoff_id')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['status', 'counting_date']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('inventory_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_count_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained('stock_lots')->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->restrictOnDelete();
            $table->unsignedInteger('expected_qty_snapshot');
            $table->unsignedInteger('counted_qty')->nullable();
            $table->integer('expected_qty_at_count')->nullable();
            $table->integer('variance_qty')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->string('review_status')->default('pending');
            $table->text('count_notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('adjustment_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['inventory_count_session_id', 'stock_lot_id'], 'count_session_lot_unique');
            $table->unique(['inventory_count_session_id', 'asset_id'], 'count_session_asset_unique');
            $table->index(['inventory_count_session_id', 'review_status'], 'count_session_review_idx');
        });

        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('maintenance_type');
            $table->string('status')->default('reported');
            $table->text('reported_issue');
            $table->text('description')->nullable();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->date('reported_date');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('service_provider')->nullable();
            $table->date('scheduled_for')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('repair_cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('findings')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
            $table->index(['status', 'scheduled_for']);
        });

        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('from_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('to_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('reason');
            $table->date('transfer_date');
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
            $table->index(['to_location_id', 'transfer_date']);
        });

        Schema::create('asset_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('event_type');
            $table->string('status')->default('pending');
            $table->string('from_lifecycle_status')->nullable();
            $table->string('to_lifecycle_status')->nullable();
            $table->string('from_condition_status')->nullable();
            $table->string('to_condition_status')->nullable();
            $table->text('reason');
            $table->string('method')->nullable();
            $table->string('supporting_reference')->nullable();
            $table->date('proposed_for')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
            $table->index(['event_type', 'proposed_for']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('category');
            $table->text('description')->nullable();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type');
            $table->char('checksum', 64);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['uploaded_by', 'created_at']);
            $table->index(['category', 'created_at']);
            $table->unique(['attachable_type', 'attachable_id', 'checksum'], 'attachments_subject_checksum_unique');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_lifecycle_status_check CHECK (lifecycle_status IN ('active','retired','disposed'))");
            DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_condition_status_check CHECK (condition_status IN ('serviceable','unserviceable','damaged'))");
            DB::statement("ALTER TABLE inventory_count_sessions ADD CONSTRAINT inventory_count_sessions_status_check CHECK (status IN ('draft','in_progress','submitted','approved','completed','cancelled'))");
            DB::statement('ALTER TABLE inventory_count_lines ADD CONSTRAINT inventory_count_lines_subject_check CHECK ((stock_lot_id IS NOT NULL AND asset_id IS NULL) OR (stock_lot_id IS NULL AND asset_id IS NOT NULL))');
            DB::statement('ALTER TABLE inventory_count_lines ADD CONSTRAINT inventory_count_lines_quantity_check CHECK (expected_qty_snapshot >= 0 AND (counted_qty IS NULL OR counted_qty >= 0) AND (expected_qty_at_count IS NULL OR expected_qty_at_count >= 0))');
            DB::statement('ALTER TABLE inventory_count_lines ADD CONSTRAINT inventory_count_lines_asset_quantity_check CHECK (asset_id IS NULL OR counted_qty IS NULL OR counted_qty <= 1)');
            DB::statement("ALTER TABLE inventory_count_lines ADD CONSTRAINT inventory_count_lines_review_check CHECK (review_status IN ('pending','approved','recount_requested','rejected'))");
            DB::statement("ALTER TABLE asset_maintenances ADD CONSTRAINT asset_maintenances_type_check CHECK (maintenance_type IN ('preventive','corrective','inspection','repair','other'))");
            DB::statement("ALTER TABLE asset_maintenances ADD CONSTRAINT asset_maintenances_status_check CHECK (status IN ('reported','scheduled','in_progress','completed','cancelled'))");
            DB::statement('ALTER TABLE asset_maintenances ADD CONSTRAINT asset_maintenances_cost_check CHECK (repair_cost IS NULL OR repair_cost >= 0)');
            DB::statement("ALTER TABLE asset_transfers ADD CONSTRAINT asset_transfers_status_check CHECK (status IN ('pending','approved','rejected','cancelled'))");
            DB::statement("ALTER TABLE asset_lifecycle_events ADD CONSTRAINT asset_lifecycle_events_type_check CHECK (event_type IN ('acquisition','condition_change','retirement','disposal'))");
            DB::statement("ALTER TABLE asset_lifecycle_events ADD CONSTRAINT asset_lifecycle_events_status_check CHECK (status IN ('pending','approved','rejected'))");
            DB::statement("CREATE UNIQUE INDEX asset_transfers_one_pending_per_asset ON asset_transfers (asset_id) WHERE status = 'pending'");
            DB::statement("CREATE UNIQUE INDEX asset_lifecycle_one_pending_per_asset ON asset_lifecycle_events (asset_id) WHERE status = 'pending'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('asset_lifecycle_events');
        Schema::dropIfExists('asset_transfers');
        Schema::dropIfExists('asset_maintenances');
        Schema::dropIfExists('inventory_count_lines');
        Schema::dropIfExists('inventory_count_sessions');

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['condition_status']);
            $table->dropIndex(['current_location_id', 'lifecycle_status']);
            $table->dropConstrainedForeignId('current_location_id');
            $table->dropColumn(['lifecycle_status', 'condition_status']);
        });

        Schema::dropIfExists('locations');
    }
};
