<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Crea empresa y sucursal de ejemplo si no existen, luego datos de prueba.
     */
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            Company::create([
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
            $company = Company::first();
        }

        if ($company && !Branch::where('company_id', $company->id)->exists()) {
            Branch::create([
                'company_id' => $company->id,
                'nombre' => 'Sucursal Principal',
                'direccion' => 'Av. Demo 123',
                'activo' => true,
            ]);
        }

        // Seeders que dependen de company/branch (tablas sin registros)
        $this->call([
            CategoriesSeeder::class,
            UnitsSeeder::class,
            AreasSeeder::class,
            CorrelativesSeeder::class,
            BrandsSeeder::class,
            SuppliersSeeder::class,
            ZonesSeeder::class,
            VehiclesSeeder::class,
            CompanyConfigSeeder::class,
            PetConfigurationSeeder::class,
            TestDataSeeder::class,
            ServiceSeeder::class,
            ClientsAndPetsSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
