<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Pet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos demo para probar logística: chofer + vehículo + citas del día.
 */
class LogisticsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            $this->command?->warn('LogisticsDemoSeeder: no hay empresa.');
            return;
        }

        $branch = Branch::where('company_id', $company->id)->first();

        $driverAttrs = [
            'name' => 'Carlos Chofer Demo',
            // El cast "hashed" del User ya cifra; no usar Hash::make aquí.
            'password' => 'chofer123456',
            'company_id' => $company->id,
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'activo')) {
            $driverAttrs['activo'] = true;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'active')) {
            $driverAttrs['active'] = true;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'user_type')) {
            $driverAttrs['user_type'] = 'company';
        }

        $driver = User::updateOrCreate(
            ['email' => 'chofer@demo.smartpet.local'],
            $driverAttrs
        );

        if (method_exists($driver, 'assignRole')) {
            try {
                $driver->assignRole('conductor');
            } catch (\Throwable $e) {
                // rol opcional según seed de permisos
            }
        }

        $vehicle = Vehicle::where('company_id', $company->id)->where('activo', true)->first();
        if ($vehicle) {
            $vehicle->update([
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
            ]);
        }

        $clients = Client::where('company_id', $company->id)->with('pets')->limit(4)->get();
        if ($clients->isEmpty() || ! $vehicle) {
            $this->command?->warn('LogisticsDemoSeeder: faltan clientes o vehículos.');
            return;
        }

        $date = now()->toDateString();
        $times = ['09:00', '10:30', '12:00', '15:00'];
        $created = 0;

        foreach ($clients->values() as $i => $client) {
            $pet = $client->pets->first() ?? Pet::where('client_id', $client->id)->first();
            if (! $pet) {
                continue;
            }

            $time = $times[$i % count($times)];
            $exists = Appointment::where('company_id', $company->id)
                ->where('client_id', $client->id)
                ->where('pet_id', $pet->id)
                ->whereDate('date', $date)
                ->where('time', $time)
                ->exists();

            if ($exists) {
                continue;
            }

            Appointment::create([
                'tracking_code' => 'SPT-DEMO-'.Str::upper(Str::random(6)),
                'booking_source' => 'demo_seed',
                'company_id' => $company->id,
                'branch_id' => $branch?->id,
                'client_id' => $client->id,
                'pet_id' => $pet->id,
                'vehicle_id' => $vehicle->id,
                'user_id' => $driver->id,
                'service_name' => $i % 2 === 0 ? 'Baño Premium' : 'Consulta Domiciliaria',
                'service_category' => $i % 2 === 0 ? 'Peluquería' : 'MovilVet',
                'service_type' => $i % 2 === 0 ? 'Peluquería' : 'Consulta',
                'date' => $date,
                'time' => $time,
                'duration' => 60,
                'address' => $client->direccion ?: 'Av. Demo 123',
                'district' => $client->distrito ?: 'Miraflores',
                'province' => $client->provincia ?: 'Lima',
                'department' => $client->departamento ?: 'Lima',
                'status' => 'Confirmada',
                'price' => $i % 2 === 0 ? 65 : 80,
                'total' => $i % 2 === 0 ? 65 : 80,
                'payment_status' => 'Pendiente',
                'notes' => 'Cita demo logística',
            ]);
            $created++;
        }

        $this->command?->info("LogisticsDemoSeeder: chofer={$driver->email} vehículo=#{$vehicle->id} citas_hoy={$created}");
        $this->command?->info('Login chofer: chofer@demo.smartpet.local / chofer123456');
    }
}
