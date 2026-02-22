<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'company_id' => 1,
                'name' => 'Baño Completo',
                'code' => 'GR-BAN-001',
                'description' => 'Baño con champú premium, secado y cepillado',
                'category' => 'Higiene',
                'area' => 'Grooming General',
                'active' => true,
                'pricing_by_size' => true,
                'pricing' => [
                    'toy' => ['price' => 30, 'cost' => 10, 'duration' => 25],
                    'small' => ['price' => 35, 'cost' => 12, 'duration' => 30],
                    'medium' => ['price' => 45, 'cost' => 15, 'duration' => 45],
                    'large' => ['price' => 65, 'cost' => 22, 'duration' => 75],
                    'xlarge' => ['price' => 95, 'cost' => 35, 'duration' => 120],
                ],
                'breed_exceptions' => [
                    ['breed' => 'Poodle', 'type' => 'multiplier', 'value' => 1.3, 'note' => 'Pelo rizado requiere más trabajo'],
                ],
                // ID 1 is Shampoo Premium based on previous tinker check
                'required_products' => [['product_id' => 1, 'quantity' => 1]],
            ],
            [
                'company_id' => 1,
                'name' => 'Corte de Pelo',
                'code' => 'GR-COR-001',
                'description' => 'Corte profesional según la raza y preferencias',
                'category' => 'Estética',
                'area' => 'Grooming General',
                'active' => true,
                'pricing_by_size' => true,
                'pricing' => [
                    'toy' => ['price' => 35, 'cost' => 12, 'duration' => 30],
                    'small' => ['price' => 40, 'cost' => 15, 'duration' => 40],
                    'medium' => ['price' => 50, 'cost' => 18, 'duration' => 60],
                    'large' => ['price' => 75, 'cost' => 28, 'duration' => 90],
                    'xlarge' => ['price' => 110, 'cost' => 40, 'duration' => 150],
                ],
                'breed_exceptions' => [
                    ['breed' => 'Poodle', 'type' => 'multiplier', 'value' => 1.5, 'note' => 'Corte específico de raza'],
                ],
                'required_products' => [],
            ],
        ];

        foreach ($services as $serviceData) {
            $service = \App\Models\Service::updateOrCreate(
                ['company_id' => $serviceData['company_id'], 'code' => $serviceData['code']],
                $serviceData
            );

            // Create corresponding Product entry for frontend visibility
            \App\Models\Product::updateOrCreate(
                ['company_id' => $service->company_id, 'code' => $service->code],
                [
                    'service_id' => $service->id,
                    'name' => $service->name,
                    'item_type' => 'SERVICIO',
                    'active' => $service->active,
                    'description' => $service->description,
                    'unit_price' => $service->pricing['small']['price'] ?? 0, // Default price for 'small'
                    'cost_price' => $service->pricing['small']['cost'] ?? 0,
                    'stock' => 999, // Services typically don't have stock limits in basic view
                ]
            );
        }
    }
}
