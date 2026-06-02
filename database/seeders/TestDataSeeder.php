<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Pet;
use App\Models\Product;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure there is an active company
        $company = Company::first();
        if (! $company) {
            $company = Company::create([
                'ruc' => '20100000001',
                'razon_social' => 'SmartPet Demo S.A.C',
                'nombre_comercial' => 'SmartPet Demo',
                'direccion' => 'Av. Demo 123',
                'ubigeo' => '150101',
                'distrito' => 'Lima',
                'provincia' => 'Lima',
                'departamento' => 'Lima',
                'usuario_sol' => 'DEMO',
                'clave_sol' => 'DEMO',
                'endpoint_beta' => 'https://api-beta.sunat.gob.pe',
                'endpoint_produccion' => 'https://api.sunat.gob.pe',
                'activo' => true,
            ]);
        } else {
            $company->update(['activo' => true]);
        }

        // 2. Client demo (sin depender de ID fijo)
        $client = Client::updateOrCreate(
            [
                'company_id' => $company->id,
                'tipo_documento' => '1',
                'numero_documento' => '12345678',
            ],
            [
                'razon_social' => 'Juan Perez',
                'email' => 'juan@example.com',
                'telefono' => '987654321',
                'nivel_fidelizacion' => 'Bronce',
            ]
        );

        // 3. Pet demo (asociada al cliente demo)
        Pet::updateOrCreate(
            [
                'company_id' => $company->id,
                'client_id' => $client->id,
                'name' => 'Tobby',
            ],
            [
                'species' => 'Perro',
                'breed' => 'Mixed',
                'size' => 'Pequeño',
                'gender' => 'Macho',
            ]
        );

        // 4. Product demo
        Product::updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Shampoo Premium',
            ],
            [
                'code' => 'PROD-SHA-001',
                'item_type' => 'PRODUCTO',
                'stock' => 10,
                'active' => true,
                'unit_price' => 20,
            ]
        );
    }
}
