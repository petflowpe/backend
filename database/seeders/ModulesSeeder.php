<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['name' => 'Catálogo', 'slug' => 'catalog', 'description' => 'Productos, categorías, unidades, marcas, proveedores', 'active' => true, 'order' => 10],
            ['name' => 'Facturación electrónica', 'slug' => 'invoicing', 'description' => 'Facturas, boletas, NC, ND, guías, SUNAT', 'active' => true, 'order' => 20],
            ['name' => 'Clientes y mascotas', 'slug' => 'pets', 'description' => 'Clientes, mascotas, citas, historial médico', 'active' => true, 'order' => 30],
            ['name' => 'Inventario', 'slug' => 'inventory', 'description' => 'Stock, kardex, movimientos', 'active' => true, 'order' => 40],
            ['name' => 'Caja', 'slug' => 'cash', 'description' => 'Sesiones de caja, movimientos, pagos', 'active' => true, 'order' => 50],
            ['name' => 'Rutas y flota', 'slug' => 'routes', 'description' => 'Vehículos, zonas, rutas, optimización', 'active' => true, 'order' => 60],
            ['name' => 'Reportes', 'slug' => 'reports', 'description' => 'Reportes y estadísticas', 'active' => true, 'order' => 70],
        ];

        foreach ($modules as $data) {
            Module::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
