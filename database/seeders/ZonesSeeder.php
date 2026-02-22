<?php

namespace Database\Seeders;

use App\Models\Zone;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ZonesSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('zones')) {
            $this->command->warn('ZonesSeeder: La tabla zones no existe. Ejecuta las migraciones.');
            return;
        }

        $company = Company::first();
        if (!$company) {
            $this->command->warn('ZonesSeeder: No hay empresa. Ejecuta DemoDataSeeder primero.');
            return;
        }

        $zones = [
            [
                'name' => 'Zona Norte',
                'color' => '#3B82F6',
                'districts' => ['Los Olivos', 'Comas', 'Independencia'],
                'coverage' => 'full',
                'demand' => 3,
                'coordinates' => null,
                'active' => true,
            ],
            [
                'name' => 'Zona Sur',
                'color' => '#10B981',
                'districts' => ['Miraflores', 'San Isidro', 'Surco'],
                'coverage' => 'full',
                'demand' => 4,
                'coordinates' => null,
                'active' => true,
            ],
            [
                'name' => 'Zona Este',
                'color' => '#F59E0B',
                'districts' => ['Santa Anita', 'Ate', 'Lurigancho'],
                'coverage' => 'partial',
                'demand' => 2,
                'coordinates' => null,
                'active' => true,
            ],
            [
                'name' => 'Zona Centro',
                'color' => '#8B5CF6',
                'districts' => ['Lima', 'Breña', 'Jesús María'],
                'coverage' => 'full',
                'demand' => 5,
                'coordinates' => null,
                'active' => true,
            ],
        ];

        foreach ($zones as $data) {
            Zone::updateOrCreate(
                ['company_id' => $company->id, 'name' => $data['name']],
                array_merge($data, ['company_id' => $company->id])
            );
        }

        $this->command->info('ZonesSeeder: ' . Zone::where('company_id', $company->id)->count() . ' zonas.');
    }
}
