<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Operational supplier seed data cannot be seeded in production.');
        }

        foreach ([
            [
                'name' => 'Metro Manila Office Solutions Corporation',
                'contact_person' => 'Marites Dela Cruz',
                'email' => 'orders@mmoffice.example',
                'phone' => '0917-110-0001',
                'address' => '118 Quezon Avenue, Quezon City, Metro Manila',
                'website' => 'https://mmoffice.example',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 5,
                'is_active' => true,
                'notes' => 'Accredited source for office, printing, and classroom consumables.',
            ],
            [
                'name' => 'Luzon Medical and Laboratory Supply, Inc.',
                'contact_person' => 'Angela Ramos',
                'email' => 'procurement@luzonmed.example',
                'phone' => '0917-110-0002',
                'address' => '42 J. P. Rizal Street, Pasig City, Metro Manila',
                'website' => 'https://luzonmed.example',
                'payment_terms' => 'Net 15',
                'lead_time_days' => 4,
                'is_active' => true,
                'notes' => 'Accredited source for clinic, laboratory, sanitation, and protective supplies.',
            ],
            [
                'name' => 'Northstar Digital Systems Corporation',
                'contact_person' => 'Rafael Mendoza',
                'email' => 'sales@northstardigital.example',
                'phone' => '0917-110-0003',
                'address' => '225 Katipunan Avenue, Quezon City, Metro Manila',
                'website' => 'https://northstardigital.example',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 10,
                'is_active' => true,
                'notes' => 'Accredited supplier of computers, network equipment, printers, and audiovisual assets.',
            ],
            [
                'name' => 'Greenfield Janitorial and Sanitation Supply',
                'contact_person' => 'Liza Fernandez',
                'email' => 'sales@greenfieldsanitation.example',
                'phone' => '0917-110-0004',
                'address' => '77 Boni Avenue, Mandaluyong City, Metro Manila',
                'website' => 'https://greenfieldsanitation.example',
                'payment_terms' => 'COD / 15 days',
                'lead_time_days' => 3,
                'is_active' => true,
                'notes' => 'Accredited source for cleaning chemicals, waste bags, and janitorial supplies.',
            ],
            [
                'name' => 'PrimeLine Furniture and Storage Solutions',
                'contact_person' => 'Eduardo Navarro',
                'email' => 'institutional@primelinefurniture.example',
                'phone' => '0917-110-0005',
                'address' => '16 Congressional Avenue, Quezon City, Metro Manila',
                'website' => 'https://primelinefurniture.example',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 14,
                'is_active' => true,
                'notes' => 'Accredited supplier of office furniture, shelving, and records-storage equipment.',
            ],
            [
                'name' => 'VoltEdge Electrical Supply Corporation',
                'contact_person' => 'Teresa Manalo',
                'email' => 'orders@voltedge.example',
                'phone' => '0917-110-0006',
                'address' => '90 Aurora Boulevard, San Juan City, Metro Manila',
                'website' => 'https://voltedge.example',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 7,
                'is_active' => true,
                'notes' => 'Accredited source for electrical supplies, lighting, and facilities maintenance materials.',
            ],
        ] as $supplierAttributes) {
            Supplier::query()->updateOrCreate(
                ['name' => $supplierAttributes['name']],
                $supplierAttributes,
            );
        }
    }
}
