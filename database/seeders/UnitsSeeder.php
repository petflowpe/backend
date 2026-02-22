<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\Company;
use Illuminate\Database\Seeder;

class UnitsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('UnitsSeeder: No hay empresa. Ejecuta DemoDataSeeder primero.');
            return;
        }

        $units = [
            ['name' => 'Unidad', 'abbreviation' => 'UND', 'sunat_code' => 'NIU', 'sort_order' => 0],
            ['name' => 'Kilogramo', 'abbreviation' => 'KG', 'sunat_code' => 'KGM', 'sort_order' => 1],
            ['name' => 'Litro', 'abbreviation' => 'L', 'sunat_code' => 'LTR', 'sort_order' => 2],
            ['name' => 'Gramo', 'abbreviation' => 'G', 'sunat_code' => 'GRM', 'sort_order' => 3],
            ['name' => 'Mililitro', 'abbreviation' => 'ML', 'sunat_code' => 'MLT', 'sort_order' => 4],
            ['name' => 'Caja', 'abbreviation' => 'CJA', 'sunat_code' => 'BX', 'sort_order' => 5],
            ['name' => 'Bolsa', 'abbreviation' => 'BOL', 'sunat_code' => 'BG', 'sort_order' => 6],
        ];

        foreach ($units as $data) {
            Unit::updateOrCreate(
                ['company_id' => $company->id, 'abbreviation' => $data['abbreviation']],
                array_merge($data, ['active' => true])
            );
        }

        $this->command->info('UnitsSeeder: ' . Unit::where('company_id', $company->id)->count() . ' unidades.');
    }
}
