<?php

namespace Database\Seeders;

use App\Models\Correlative;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class CorrelativesSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::first();
        if (!$branch) {
            $this->command->warn('CorrelativesSeeder: No hay sucursal. Ejecuta DemoDataSeeder primero.');
            return;
        }

        $correlatives = [
            ['tipo_documento' => '01', 'serie' => 'F001', 'correlativo_actual' => 0], // Factura
            ['tipo_documento' => '03', 'serie' => 'B001', 'correlativo_actual' => 0], // Boleta
            ['tipo_documento' => '07', 'serie' => 'FC01', 'correlativo_actual' => 0], // Nota de crédito
            ['tipo_documento' => '08', 'serie' => 'FD01', 'correlativo_actual' => 0], // Nota de débito
            ['tipo_documento' => '09', 'serie' => 'T001', 'correlativo_actual' => 0], // Guía de remisión
        ];

        foreach ($correlatives as $data) {
            Correlative::updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'tipo_documento' => $data['tipo_documento'],
                    'serie' => $data['serie'],
                ],
                ['correlativo_actual' => $data['correlativo_actual']]
            );
        }

        $this->command->info('CorrelativesSeeder: ' . Correlative::where('branch_id', $branch->id)->count() . ' correlativos.');
    }
}
