<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UbiDistritoSeeder extends Seeder
{
    /**
     * Run the database seeds. Idempotente: usa insertOrIgnore para no fallar si los distritos ya existen.
     */
    public function run(): void
    {
        $filePath = public_path('data_ubi.txt');

        if (!file_exists($filePath)) {
            $this->command->error('File data_ubi.txt not found in public directory');
            return;
        }

        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $file = fopen($filePath, 'r');
        fgetcsv($file, 0, '|'); // Skip header line

        $batchSize = 1000;
        $batch = [];
        $count = 0;

        while (($row = fgetcsv($file, 0, '|')) !== false) {
            if (count($row) === 5) {
                $batch[] = [
                    'id' => trim($row[0]),
                    'nombre' => trim($row[1]),
                    'info_busqueda' => trim($row[2]),
                    'provincia_id' => trim($row[3]),
                    'region_id' => trim($row[4]),
                ];

                $count++;

                if (count($batch) >= $batchSize) {
                    DB::table('ubi_distritos')->insertOrIgnore($batch);
                    $batch = [];
                    $this->command->info("Processed batch of {$batchSize} records. Total: {$count}");
                }
            }
        }

        if (!empty($batch)) {
            DB::table('ubi_distritos')->insertOrIgnore($batch);
        }

        fclose($file);

        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info("Successfully processed {$count} districts from data_ubi.txt");
    }
}
