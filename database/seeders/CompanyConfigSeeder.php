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

        \App\Models\CompanyConfiguration::updateOrCreate(
            [
                'company_id' => $company->id,
                'config_type' => 'calendar_settings',
                'environment' => 'general',
                'service_type' => 'general',
            ],
            [
                'company_id' => $company->id,
                'config_type' => 'calendar_settings',
                'environment' => 'general',
                'service_type' => 'general',
                'config_data' => [
                    'show_weekends' => true,
                    'interval_minutes' => 15,
                    'first_day_of_week' => 1,
                    'first_hour' => 8,
                    'last_hour' => 20,
                    'show_day_view_option' => true,
                    'day_view_first_hour' => 8,
                    'day_view_last_hour' => 18,
                    'default_view_current_day' => true,
                    'allow_booking_outside_hours' => false,
                    'worked_hours_per_day' => 8,
                    'daily_plan_enabled' => false,
                    'internal_reservations_enabled' => false,
                    'client_labels_enabled' => true,
                    'create_task_unpaid_invoices' => false,
                    'show_schedules_shift_types' => false,
                    'change_colors_by_status_reason' => false,
                    'show_only_national_holidays' => false,
                    'warn_if_no_visit_reason' => false,
                ],
                'is_active' => true,
                'description' => 'Opciones de calendario y reserva',
            ]
        );
    }
}
