<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Company;
use Illuminate\Database\Seeder;

class SuppliersSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('SuppliersSeeder: No hay empresa. Ejecuta DemoDataSeeder primero.');
            return;
        }

        $suppliers = [
            [
                'name' => 'Distribuidora Pet Perú S.A.C.',
                'business_name' => 'Distribuidora Pet Perú S.A.C.',
                'document_type' => 'RUC',
                'document_number' => '20100000001',
                'email' => 'ventas@petperu.com',
                'phone' => '01-2345678',
                'address' => 'Av. Industrial 123, Lima',
                'notes' => 'Proveedor principal de alimentos',
                'sort_order' => 0,
            ],
            [
                'name' => 'Insumos Veterinarios EIRL',
                'business_name' => 'Insumos Veterinarios EIRL',
                'document_type' => 'RUC',
                'document_number' => '20100000002',
                'email' => 'contacto@insumosvet.com',
                'phone' => '01-8765432',
                'address' => 'Calle Los Olivos 456',
                'notes' => 'Medicamentos e insumos',
                'sort_order' => 1,
            ],
            [
                'name' => 'Mayorista Grooming',
                'business_name' => null,
                'document_type' => 'RUC',
                'document_number' => '20100000003',
                'email' => 'info@mayoristagrooming.com',
                'phone' => '991234567',
                'address' => 'Jr. Grooming 789',
                'notes' => 'Shampoos y productos de peluquería',
                'sort_order' => 2,
            ],
        ];

        foreach ($suppliers as $data) {
            Supplier::updateOrCreate(
                ['company_id' => $company->id, 'document_number' => $data['document_number']],
                array_merge($data, ['active' => true])
            );
        }

        $this->command->info('SuppliersSeeder: ' . Supplier::where('company_id', $company->id)->count() . ' proveedores.');
    }
}
