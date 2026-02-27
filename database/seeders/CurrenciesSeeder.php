<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'PEN', 'name' => 'Sol peruano', 'symbol' => 'S/', 'decimal_places' => 2, 'active' => true, 'is_default' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'active' => true, 'is_default' => false],
            ['code' => 'BRL', 'name' => 'Real brasileño', 'symbol' => 'R$', 'decimal_places' => 2, 'active' => true, 'is_default' => false],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'active' => true, 'is_default' => false],
        ];

        foreach ($currencies as $data) {
            Currency::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
