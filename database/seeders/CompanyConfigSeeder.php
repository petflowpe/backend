<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanyConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = \App\Models\Company::first();
        if (!$company) {
            $this->command->warn('CompanyConfigSeeder: No hay empresa. Ejecuta DemoDataSeeder primero.');
            return;
        }

        \App\Models\CompanyConfiguration::updateOrCreate(
            [
                'company_id' => $company->id,
                'config_type' => 'document_settings',
                'environment' => 'general',
                'service_type' => 'general',
            ],
            [
            'company_id' => $company->id,
            'config_type' => 'document_settings',
            'environment' => 'general',
            'service_type' => 'general',
            'config_data' => [
                'working_hours' => [
                    'monday' => ['open' => true, 'start' => '08:00', 'end' => '18:00'],
                    'tuesday' => ['open' => true, 'start' => '08:00', 'end' => '18:00'],
                    'wednesday' => ['open' => true, 'start' => '08:00', 'end' => '18:00'],
                    'thursday' => ['open' => true, 'start' => '08:00', 'end' => '18:00'],
                    'friday' => ['open' => true, 'start' => '08:00', 'end' => '18:00'],
                    'saturday' => ['open' => true, 'start' => '09:00', 'end' => '14:00'],
                    'sunday' => ['open' => false, 'start' => '00:00', 'end' => '00:00'],
                ],
                'holidays' => [
                    '2026-01-01',
                    '2026-12-25',
                ]
            ],
            'is_active' => true,
            'description' => 'Horarios de trabajo y configuración general'
            ]
        );
    }
}
