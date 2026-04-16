<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_status_check');

        DB::statement("UPDATE assets SET status = 'Available' WHERE status = 'in_stock'");
        DB::statement("UPDATE assets SET status = 'Checked_Out' WHERE status = 'checked_out'");
        DB::statement("UPDATE assets SET status = 'Condemned' WHERE status = 'retired'");
        DB::statement("UPDATE assets SET status = 'Unserviceable' WHERE status = 'damaged'");

        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_status_check CHECK (status IN ('Available','Checked_Out','Unserviceable','Condemned'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_status_check');

        DB::statement("UPDATE assets SET status = 'in_stock' WHERE status = 'Available'");
        DB::statement("UPDATE assets SET status = 'checked_out' WHERE status = 'Checked_Out'");
        DB::statement("UPDATE assets SET status = 'retired' WHERE status = 'Condemned'");
        DB::statement("UPDATE assets SET status = 'damaged' WHERE status = 'Unserviceable'");

        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_status_check CHECK (status IN ('in_stock','checked_out','retired','damaged'))");
    }
};
