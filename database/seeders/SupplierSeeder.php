<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'name' => 'Campus Office Supplies Co.',
                'contact_person' => 'Marites Dela Cruz',
                'email' => 'orders@campusoffice.test',
                'phone' => '0917-110-0001',
                'address' => 'Quezon Avenue, Quezon City',
                'website' => 'https://campusoffice.test',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 5,
                'is_active' => true,
                'notes' => 'Primary supplier for office and classroom consumables.',
            ],
            [
                'name' => 'Meditech Distribution',
                'contact_person' => 'Angela Ramos',
                'email' => 'procurement@meditech.test',
                'phone' => '0917-110-0002',
                'address' => 'JP Rizal Street, Pasig City',
                'website' => 'https://meditech.test',
                'payment_terms' => 'Net 15',
                'lead_time_days' => 4,
                'is_active' => true,
                'notes' => 'Medical, sanitation, and protective supply partner.',
            ],
            [
                'name' => 'Digital Axis Systems',
                'contact_person' => 'Rafael Mendoza',
                'email' => 'sales@digitalaxis.test',
                'phone' => '0917-110-0003',
                'address' => 'Katipunan Avenue, Quezon City',
                'website' => 'https://digitalaxis.test',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 10,
                'is_active' => true,
                'notes' => 'IT hardware and tagged asset supplier.',
            ],
            [
                'name' => 'CleanEdge Trading',
                'contact_person' => 'Liza Fernandez',
                'email' => 'hello@cleanedge.test',
                'phone' => '0917-110-0004',
                'address' => 'Boni Avenue, Mandaluyong City',
                'website' => 'https://cleanedge.test',
                'payment_terms' => 'COD / 15 days',
                'lead_time_days' => 3,
                'is_active' => true,
                'notes' => 'Fast-moving cleaning and janitorial supplies.',
            ],
        ] as $supplierAttributes) {
            Supplier::query()->updateOrCreate(
                ['name' => $supplierAttributes['name']],
                $supplierAttributes,
            );
        }
    }
}
