<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE requisitions DROP CONSTRAINT IF EXISTS requisitions_status_check');
        DB::statement("ALTER TABLE requisitions ADD CONSTRAINT requisitions_status_check CHECK (status IN ('Draft','Submitted','Approved','PartiallyIssued','Backordered','Issued','Closed','Rejected'))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE requisitions DROP CONSTRAINT IF EXISTS requisitions_status_check');
        DB::statement("ALTER TABLE requisitions ADD CONSTRAINT requisitions_status_check CHECK (status IN ('Draft','Submitted','Approved','Issued','Closed','Rejected'))");
    }
};
