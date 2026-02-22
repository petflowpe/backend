<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class VehiclesSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('vehicles')) {
            $this->command->warn('VehiclesSeeder: La tabla vehicles no existe. Ejecuta las migraciones.');
            return;
        }

        $company = Company::first();
        if (!$company) {
            $this->command->warn('VehiclesSeeder: No hay empresa. Ejecuta DemoDataSeeder primero.');
            return;
        }

        $vehicles = [
            [
                'name' => 'Furgoneta 1',
                'type' => 'furgoneta_grande',
                'placa' => 'ABC-123',
                'marca' => 'Hyundai',
                'modelo' => 'H-100',
                'anio' => 2022,
                'color' => 'Blanco',
                'capacidad_slots' => 10,
                'capacidad_por_categoria' => ['oro' => 2.0, 'plata' => 1.5, 'bronce' => 1.0],
                'zonas_asignadas' => [1, 2],
                'activo' => true,
                'horario_disponibilidad' => [
                    'monday' => ['start' => '08:00', 'end' => '18:00'],
                    'tuesday' => ['start' => '08:00', 'end' => '18:00'],
                    'wednesday' => ['start' => '08:00', 'end' => '18:00'],
                    'thursday' => ['start' => '08:00', 'end' => '18:00'],
                    'friday' => ['start' => '08:00', 'end' => '18:00'],
                    'saturday' => ['start' => '09:00', 'end' => '14:00'],
                ],
                'equipamiento' => ['Bañera portátil', 'Secador', 'Mesa de grooming'],
            ],
            [
                'name' => 'Auto Compacto 1',
                'type' => 'auto_compacto',
                'placa' => 'XYZ-456',
                'marca' => 'Toyota',
                'modelo' => 'Yaris',
                'anio' => 2021,
                'color' => 'Gris',
                'capacidad_slots' => 4,
                'capacidad_por_categoria' => ['oro' => 1.0, 'plata' => 1.0, 'bronce' => 1.0],
                'zonas_asignadas' => [3],
                'activo' => true,
                'horario_disponibilidad' => [
                    'monday' => ['start' => '09:00', 'end' => '17:00'],
                    'tuesday' => ['start' => '09:00', 'end' => '17:00'],
                    'wednesday' => ['start' => '09:00', 'end' => '17:00'],
                    'thursday' => ['start' => '09:00', 'end' => '17:00'],
                    'friday' => ['start' => '09:00', 'end' => '17:00'],
                ],
                'equipamiento' => ['Kit básico de grooming'],
            ],
        ];

        foreach ($vehicles as $data) {
            Vehicle::updateOrCreate(
                ['company_id' => $company->id, 'name' => $data['name']],
                array_merge($data, ['company_id' => $company->id])
            );
        }

        $this->command->info('VehiclesSeeder: ' . Vehicle::where('company_id', $company->id)->count() . ' vehículos.');
    }
}
