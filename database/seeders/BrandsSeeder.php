<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Company;
use Illuminate\Database\Seeder;

class BrandsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('BrandsSeeder: No hay empresa. Ejecuta DemoDataSeeder primero.');
            return;
        }

        $brands = [
            ['name' => 'Pro Plan', 'description' => 'Alimentos premium para mascotas', 'website' => 'https://www.purina.com', 'sort_order' => 0],
            ['name' => 'Royal Canin', 'description' => 'Nutrición específica por raza', 'website' => 'https://www.royalcanin.com', 'sort_order' => 1],
            ['name' => 'Hartz', 'description' => 'Productos de higiene y cuidado', 'sort_order' => 2],
            ['name' => 'Champion', 'description' => 'Shampoos y productos de grooming', 'sort_order' => 3],
            ['name' => 'Genérico', 'description' => 'Productos sin marca específica', 'sort_order' => 4],
        ];

        foreach ($brands as $data) {
            Brand::updateOrCreate(
                ['company_id' => $company->id, 'name' => $data['name']],
                array_merge($data, ['active' => true])
            );
        }

        $this->command->info('BrandsSeeder: ' . Brand::where('company_id', $company->id)->count() . ' marcas.');
    }
}
