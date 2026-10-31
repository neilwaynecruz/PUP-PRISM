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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('unit_of_measurement_id')->nullable()->after('supplier_id')
                ->constrained('unit_of_measurements')->nullOnDelete();
            $table->string('variant')->nullable()->after('name');
            $table->string('brand')->nullable()->after('variant');
            $table->string('model')->nullable()->after('brand');
            $table->string('color', 80)->nullable()->after('model');
            $table->string('size', 120)->nullable()->after('color');
            $table->json('specifications')->nullable()->after('size');
            $table->boolean('is_bookable')->default(false)->after('is_active');

            $table->index(['unit_of_measurement_id', 'is_active']);
            $table->index(['category_id', 'is_bookable']);
            $table->index(['brand', 'model']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('parent_location_id')->nullable()->after('department_id')
                ->constrained('locations')->nullOnDelete();
            $table->string('facility_group', 40)->nullable()->after('name');
            $table->string('location_type', 80)->nullable()->after('facility_group');
            $table->boolean('is_bookable')->default(false)->after('room');
            $table->unsignedInteger('capacity')->nullable()->after('is_bookable');

            $table->index(['facility_group', 'location_type']);
            $table->index(['is_bookable', 'is_active']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->string('serial_number', 120)->nullable()->unique()->after('tag_code');
            $table->date('acquisition_date')->nullable()->after('condition_status');
            $table->decimal('acquisition_cost', 12, 2)->nullable()->after('acquisition_date');
            $table->date('warranty_expires_at')->nullable()->after('acquisition_cost');
            $table->date('installed_at')->nullable()->after('warranty_expires_at');
            $table->unsignedSmallInteger('maintenance_interval_days')->nullable()->after('installed_at');

            $table->index(['acquisition_date', 'condition_status']);
            $table->index(['warranty_expires_at', 'lifecycle_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['warranty_expires_at', 'lifecycle_status']);
            $table->dropIndex(['acquisition_date', 'condition_status']);
            $table->dropUnique(['serial_number']);
            $table->dropColumn([
                'serial_number',
                'acquisition_date',
                'acquisition_cost',
                'warranty_expires_at',
                'installed_at',
                'maintenance_interval_days',
            ]);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['is_bookable', 'is_active']);
            $table->dropIndex(['facility_group', 'location_type']);
            $table->dropConstrainedForeignId('parent_location_id');
            $table->dropColumn(['facility_group', 'location_type', 'is_bookable', 'capacity']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['brand', 'model']);
            $table->dropIndex(['category_id', 'is_bookable']);
            $table->dropIndex(['unit_of_measurement_id', 'is_active']);
            $table->dropConstrainedForeignId('unit_of_measurement_id');
            $table->dropColumn([
                'variant',
                'brand',
                'model',
                'color',
                'size',
                'specifications',
                'is_bookable',
            ]);
        });
    }
};
