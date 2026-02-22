<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Company;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class AreasSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('AreasSeeder: No hay empresa. Ejecuta DemoDataSeeder primero.');
            return;
        }

        $branch = Branch::where('company_id', $company->id)->first();

        $areas = [
            ['name' => 'Recepción', 'description' => 'Atención al cliente y recepción de mascotas', 'location' => 'Planta baja', 'sort_order' => 0],
            ['name' => 'Grooming', 'description' => 'Área de baño y corte', 'location' => 'Planta baja', 'sort_order' => 1],
            ['name' => 'Consultorio', 'description' => 'Consultas veterinarias', 'location' => 'Primer piso', 'sort_order' => 2],
            ['name' => 'Almacén', 'description' => 'Productos e insumos', 'location' => 'Sótano', 'sort_order' => 3],
        ];

        foreach ($areas as $data) {
            Area::updateOrCreate(
                ['company_id' => $company->id, 'name' => $data['name']],
                array_merge($data, [
                    'branch_id' => $branch?->id,
                    'active' => true,
                ])
            );
        }

        $this->command->info('AreasSeeder: ' . Area::where('company_id', $company->id)->count() . ' áreas.');
    }
}
