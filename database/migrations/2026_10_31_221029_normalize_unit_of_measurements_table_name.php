<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An earlier draft of the units migration created `units_of_measurement`, while the
     * migration and model now use Laravel's conventional `unit_of_measurements`. Rename the
     * draft table wherever it exists so every database converges on one name. Foreign keys
     * follow the table on PostgreSQL and SQLite.
     */
    public function up(): void
    {
        if (Schema::hasTable('units_of_measurement') && ! Schema::hasTable('unit_of_measurements')) {
            Schema::rename('units_of_measurement', 'unit_of_measurements');
        }
    }

    /**
     * Intentionally a no-op: the draft table name should not be restored.
     */
    public function down(): void
    {
        //
    }
};
