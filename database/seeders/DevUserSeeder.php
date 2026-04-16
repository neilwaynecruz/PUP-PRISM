<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DevUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UatOrganizationSeeder::class);
    }
}
