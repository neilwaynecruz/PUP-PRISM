<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call([
            UatOrganizationSeeder::class,
            SupplierSeeder::class,
            UatInventorySeeder::class,
            UatProcurementSeeder::class,
            UatWorkflowSeeder::class,
            UatAssetOperationsSeeder::class,
            UatOperationalHistorySeeder::class,
            UatMonitoringSeeder::class,
        ]);
    }
}
