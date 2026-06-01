<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Database\Seeder;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('ProductsSeeder: No hay empresa. Ejecuta DemoDataSeeder primero.');
            return;
        }

        /** @var ProductService $service */
        $service = app(ProductService::class);

        $area = Area::where('company_id', $company->id)->where('name', 'Almacén')->first()
            ?? Area::where('company_id', $company->id)->first();

        $catalog = [
            ['name' => 'Shampoo Hipoalergénico', 'category' => 'Higiene', 'brand' => 'PetCare', 'unit_price' => 28.50, 'cost_price' => 14.00, 'stock' => 24, 'min_stock' => 5],
            ['name' => 'Acondicionador Desenredante', 'category' => 'Higiene', 'brand' => 'PetCare', 'unit_price' => 32.00, 'cost_price' => 16.50, 'stock' => 18, 'min_stock' => 4],
            ['name' => 'Cepillo Slicker Profesional', 'category' => 'Estética', 'brand' => 'GroomPro', 'unit_price' => 45.00, 'cost_price' => 22.00, 'stock' => 12, 'min_stock' => 3],
            ['name' => 'Tijera Curva 7"', 'category' => 'Estética', 'brand' => 'GroomPro', 'unit_price' => 120.00, 'cost_price' => 75.00, 'stock' => 6, 'min_stock' => 2],
            ['name' => 'Antipulgas Spot-On', 'category' => 'Salud', 'brand' => 'VetShield', 'unit_price' => 38.90, 'cost_price' => 21.00, 'stock' => 30, 'min_stock' => 8],
            ['name' => 'Snack Dental Perro', 'category' => 'Alimentación', 'brand' => 'NutriPet', 'unit_price' => 18.50, 'cost_price' => 9.20, 'stock' => 40, 'min_stock' => 10],
            ['name' => 'Correa Ajustable Nylon', 'category' => 'Accesorios', 'brand' => 'WalkMate', 'unit_price' => 25.00, 'cost_price' => 11.50, 'stock' => 15, 'min_stock' => 4],
            ['name' => 'Toalla Microfibra Grooming', 'category' => 'Higiene', 'brand' => 'PetCare', 'unit_price' => 22.00, 'cost_price' => 10.00, 'stock' => 20, 'min_stock' => 5],
        ];

        $created = 0;
        foreach ($catalog as $item) {
            $category = Category::where('company_id', $company->id)->where('name', $item['category'])->first();
            $brand = Brand::where('company_id', $company->id)->where('name', $item['brand'])->first();

            $exists = Product::where('company_id', $company->id)->where('name', $item['name'])->exists();
            if ($exists) {
                continue;
            }

            $service->create([
                'company_id' => $company->id,
                'name' => $item['name'],
                'category_id' => $category?->id,
                'brand_id' => $brand?->id,
                'area_id' => $area?->id,
                'item_type' => 'PRODUCTO',
                'unit' => 'NIU',
                'unit_price' => $item['unit_price'],
                'cost_price' => $item['cost_price'],
                'stock' => $item['stock'],
                'min_stock' => $item['min_stock'],
                'active' => true,
            ]);
            $created++;
        }

        $this->command->info("ProductsSeeder: {$created} productos demo creados.");
    }
}
