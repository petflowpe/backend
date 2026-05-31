<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {

        $this->call([
            UbiRegionesSeeder::class,
            UbiProvinciasSeeder::class,
            UbiDistritoSeeder::class,
            RolesAndPermissionsSeeder::class,
            CurrenciesSeeder::class,
            ModulesSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
