<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure Company 1 exists and is active
        $company = \App\Models\Company::find(1);
        if ($company) {
            $company->update(['activo' => true]);
        }

        // 2. Client - ID 1 (Bronce)
        \App\Models\Client::updateOrCreate(
            ['id' => 1],
            [
                'company_id' => 1,
                'tipo_documento' => '1', // DNI
                'numero_documento' => '12345678',
                'razon_social' => 'Juan Perez',
                'email' => 'juan@example.com',
                'telefono' => '987654321',
                'nivel_fidelizacion' => 'Bronce'
            ]
        );

        // 3. Pet
        \App\Models\Pet::updateOrCreate(
            ['id' => 1],
            [
                'client_id' => 1,
                'company_id' => 1,
                'name' => 'Tobby',
                'species' => 'Perro',
                'breed' => 'Mixed',
                'size' => 'Pequeño',
                'gender' => 'Macho'
            ]
        );

        // 4. Product
        \App\Models\Product::updateOrCreate(
            ['id' => 1],
            [
                'company_id' => 1,
                'name' => 'Shampoo Premium',
                'code' => 'PROD-SHA-001',
                'item_type' => 'PRODUCTO',
                'stock' => 10,
                'active' => true,
                'unit_price' => 20
            ]
        );
    }
}
