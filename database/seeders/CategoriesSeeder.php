<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('CategoriesSeeder: No hay empresa. Ejecuta DemoDataSeeder primero.');
            return;
        }

        $categories = [
            ['name' => 'Higiene', 'description' => 'Shampoos, acondicionadores, productos de limpieza', 'color' => 'blue', 'icon' => 'Droplets', 'sort_order' => 0],
            ['name' => 'Estética', 'description' => 'Cortes, peinados, accesorios', 'color' => 'purple', 'icon' => 'Scissors', 'sort_order' => 1],
            ['name' => 'Salud', 'description' => 'Medicamentos, vitaminas, antiparasitarios', 'color' => 'green', 'icon' => 'Heart', 'sort_order' => 2],
            ['name' => 'Alimentación', 'description' => 'Alimentos, snacks, suplementos', 'color' => 'orange', 'icon' => 'Package', 'sort_order' => 3],
            ['name' => 'Accesorios', 'description' => 'Correas, juguetes, camas', 'color' => 'red', 'icon' => 'ShoppingBag', 'sort_order' => 4],
        ];

        foreach ($categories as $data) {
            Category::updateOrCreate(
                ['company_id' => $company->id, 'name' => $data['name']],
                array_merge($data, ['active' => true])
            );
        }

        $this->command->info('CategoriesSeeder: ' . Category::where('company_id', $company->id)->count() . ' categorías.');
    }
}
