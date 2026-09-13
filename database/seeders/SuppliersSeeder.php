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
                'supplier_type' => 'Mercadería',
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
                'supplier_type' => 'Mercadería',
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
                'supplier_type' => 'Mercadería',
                'email' => 'info@mayoristagrooming.com',
                'phone' => '991234567',
                'address' => 'Jr. Grooming 789',
                'notes' => 'Shampoos y productos de peluquería',
                'sort_order' => 2,
            ],
            [
                'name' => 'Dra. Laura Mendoza',
                'business_name' => null,
                'document_type' => 'DNI',
                'document_number' => '45678901',
                'supplier_type' => 'Médico Externo',
                'specialty' => 'Dermatología',
                'professional_license' => 'CMVP 1122',
                'clinic_name' => 'Dermavet Lima',
                'fee_rate' => 180,
                'email' => 'laura.mendoza@dermavet.pe',
                'billing_email' => 'laura.mendoza@dermavet.pe',
                'phone' => '999111222',
                'contact_name' => 'Asistente Carla',
                'address' => 'Av. Javier Prado 1500, San Isidro',
                'notes' => 'Referidos de piel y alergias',
                'accounting_account_code' => '632',
                'sort_order' => 3,
            ],
            [
                'name' => 'Dr. Diego Salazar',
                'business_name' => null,
                'document_type' => 'DNI',
                'document_number' => '46789012',
                'supplier_type' => 'Médico Externo',
                'specialty' => 'Cirugía',
                'professional_license' => 'CMVP 3344',
                'clinic_name' => 'Cirugía Animal Sur',
                'fee_rate' => 250,
                'email' => 'diego.salazar@cirugiasur.pe',
                'billing_email' => 'diego.salazar@cirugiasur.pe',
                'phone' => '998222333',
                'address' => 'Calle Las Begonias 220, Surco',
                'notes' => 'Cirugías electivas y urgencias',
                'accounting_account_code' => '632',
                'sort_order' => 4,
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
