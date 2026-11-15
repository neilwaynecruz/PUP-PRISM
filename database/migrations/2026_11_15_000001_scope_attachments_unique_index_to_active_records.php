<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attachments')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropUnique('attachments_subject_checksum_unique');
        });

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS attachments_subject_active_checksum_unique ON attachments (attachable_type, attachable_id, checksum) WHERE deleted_at IS NULL',
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('attachments')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS attachments_subject_active_checksum_unique');
        }

        Schema::table('attachments', function (Blueprint $table) {
            $table->unique(['attachable_type', 'attachable_id', 'checksum'], 'attachments_subject_checksum_unique');
        });
    }
};
