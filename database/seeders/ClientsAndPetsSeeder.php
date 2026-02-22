<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ClientsAndPetsSeeder extends Seeder
{
    /**
     * Crea clientes de prueba con mascotas y datos completos.
     */
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No hay empresa. Ejecuta DemoDataSeeder primero.');
            return;
        }
        $companyId = $company->id;
        $today = Carbon::today()->toDateString();

        $clientsData = [
            [
                'tipo_documento' => '1',
                'numero_documento' => '12345678',
                'razon_social' => 'Juan Pérez García',
                'nombre_comercial' => null,
                'direccion' => 'Av. Benavides 1234, Miraflores',
                'ubigeo' => '150122',
                'distrito' => 'Miraflores',
                'provincia' => 'Lima',
                'departamento' => 'Lima',
                'zona_preferida' => 'Miraflores',
                'telefono' => '987654321',
                'telefono2' => '991234567',
                'email' => 'juan.perez@email.com',
                'fecha_nacimiento' => '1985-03-15',
                'genero' => 'Masculino',
                'notas' => 'Cliente preferente, horario tarde.',
                'preferencias_contacto' => ['email', 'whatsapp'],
                'puntos_fidelizacion' => 120,
                'nivel_fidelizacion' => 'Bronce',
                'fecha_ultima_visita' => $today,
                'fecha_registro' => '2024-01-10',
                'activo' => true,
                'pets' => [
                    ['name' => 'Tobby', 'species' => 'Perro', 'breed' => 'Golden Retriever', 'size' => 'Grande', 'gender' => 'Macho', 'color' => 'Dorado', 'temperament' => 'Dócil', 'birth_date' => '2022-05-10', 'weight' => 28.5, 'sterilized' => true, 'notes' => 'Muy sociable'],
                    ['name' => 'Luna', 'species' => 'Gato', 'breed' => 'Siamés', 'size' => 'Mediano', 'gender' => 'Hembra', 'color' => 'Crema y negro', 'temperament' => 'Tranquilo', 'birth_date' => '2023-01-20', 'weight' => 4.2, 'sterilized' => true],
                ],
            ],
            [
                'tipo_documento' => '1',
                'numero_documento' => '23456789',
                'razon_social' => 'María González López',
                'nombre_comercial' => null,
                'direccion' => 'Calle Los Pinos 456, San Isidro',
                'ubigeo' => '150131',
                'distrito' => 'San Isidro',
                'provincia' => 'Lima',
                'departamento' => 'Lima',
                'zona_preferida' => 'San Isidro',
                'telefono' => '998765432',
                'telefono2' => null,
                'email' => 'maria.gonzalez@email.com',
                'fecha_nacimiento' => '1990-07-22',
                'genero' => 'Femenino',
                'notas' => null,
                'preferencias_contacto' => ['whatsapp'],
                'puntos_fidelizacion' => 85,
                'nivel_fidelizacion' => 'Plata',
                'fecha_ultima_visita' => $today,
                'fecha_registro' => '2024-03-05',
                'activo' => true,
                'pets' => [
                    ['name' => 'Max', 'species' => 'Perro', 'breed' => 'Bulldog Francés', 'size' => 'Mediano', 'gender' => 'Macho', 'color' => 'Atigrado', 'temperament' => 'Juguetón', 'birth_date' => '2023-06-15', 'weight' => 12.0, 'sterilized' => false, 'allergies' => ['polen'], 'notes' => 'Alergia leve a polen'],
                ],
            ],
            [
                'tipo_documento' => '1',
                'numero_documento' => '34567890',
                'razon_social' => 'Carlos Rodríguez Vega',
                'nombre_comercial' => null,
                'direccion' => 'Av. La Molina 789, La Molina',
                'ubigeo' => '150114',
                'distrito' => 'La Molina',
                'provincia' => 'Lima',
                'departamento' => 'Lima',
                'zona_preferida' => 'La Molina',
                'telefono' => '977654321',
                'telefono2' => '966543210',
                'email' => 'carlos.rodriguez@email.com',
                'fecha_nacimiento' => '1978-11-08',
                'genero' => 'Masculino',
                'notas' => 'Varios perros, agenda fija semanal.',
                'preferencias_contacto' => ['email', 'telefono'],
                'puntos_fidelizacion' => 250,
                'nivel_fidelizacion' => 'Oro',
                'fecha_ultima_visita' => $today,
                'fecha_registro' => '2023-08-20',
                'activo' => true,
                'pets' => [
                    ['name' => 'Rocky', 'species' => 'Perro', 'breed' => 'Pastor Alemán', 'size' => 'Grande', 'gender' => 'Macho', 'color' => 'Negro y fuego', 'temperament' => 'Protector', 'birth_date' => '2021-02-28', 'weight' => 35.0, 'sterilized' => true, 'microchip' => '985112345678901', 'notes' => 'Perro guardián'],
                    ['name' => 'Nala', 'species' => 'Perro', 'breed' => 'Labrador', 'size' => 'Grande', 'gender' => 'Hembra', 'color' => 'Amarillo', 'temperament' => 'Dócil', 'birth_date' => '2022-09-12', 'weight' => 26.5, 'sterilized' => true],
                    ['name' => 'Coco', 'species' => 'Perro', 'breed' => 'Chihuahua', 'size' => 'Pequeño', 'gender' => 'Macho', 'color' => 'Café', 'temperament' => 'Nervioso', 'birth_date' => '2024-01-05', 'weight' => 2.8, 'sterilized' => false],
                ],
            ],
            [
                'tipo_documento' => '1',
                'numero_documento' => '45678901',
                'razon_social' => 'Ana Martínez Flores',
                'nombre_comercial' => null,
                'direccion' => 'Jr. Los Olivos 321, Santiago de Surco',
                'ubigeo' => '150140',
                'distrito' => 'Santiago de Surco',
                'provincia' => 'Lima',
                'departamento' => 'Lima',
                'zona_preferida' => 'Surco',
                'telefono' => '955432109',
                'telefono2' => null,
                'email' => 'ana.martinez@email.com',
                'fecha_nacimiento' => '1995-04-30',
                'genero' => 'Femenino',
                'notas' => null,
                'preferencias_contacto' => ['whatsapp', 'email'],
                'puntos_fidelizacion' => 45,
                'nivel_fidelizacion' => 'Bronce',
                'fecha_ultima_visita' => Carbon::today()->subDays(15)->toDateString(),
                'fecha_registro' => '2024-10-01',
                'activo' => true,
                'pets' => [
                    ['name' => 'Michi', 'species' => 'Gato', 'breed' => 'Persa', 'size' => 'Mediano', 'gender' => 'Hembra', 'color' => 'Blanco', 'temperament' => 'Tranquilo', 'birth_date' => '2023-03-18', 'weight' => 4.5, 'sterilized' => true, 'notes' => 'Pelo largo, cepillado frecuente'],
                    ['name' => 'Simba', 'species' => 'Gato', 'breed' => 'Mestizo', 'size' => 'Mediano', 'gender' => 'Macho', 'color' => 'Naranja', 'temperament' => 'Juguetón', 'birth_date' => '2024-06-01', 'weight' => 3.0, 'sterilized' => false],
                ],
            ],
            [
                'tipo_documento' => '4',
                'numero_documento' => 'CE00123456',
                'razon_social' => 'Pedro Sánchez Díaz',
                'nombre_comercial' => null,
                'direccion' => 'Av. Angamos Este 555, Surquillo',
                'ubigeo' => '150141',
                'distrito' => 'Surquillo',
                'provincia' => 'Lima',
                'departamento' => 'Lima',
                'zona_preferida' => 'Surquillo',
                'telefono' => '944321098',
                'telefono2' => '933210987',
                'email' => 'pedro.sanchez@email.com',
                'fecha_nacimiento' => '1982-12-12',
                'genero' => 'Masculino',
                'notas' => 'Extranjero, CE.',
                'preferencias_contacto' => ['email'],
                'puntos_fidelizacion' => 0,
                'nivel_fidelizacion' => 'Bronce',
                'fecha_ultima_visita' => null,
                'fecha_registro' => $today,
                'activo' => true,
                'pets' => [
                    ['name' => 'Thor', 'species' => 'Perro', 'breed' => 'Husky Siberiano', 'size' => 'Grande', 'gender' => 'Macho', 'color' => 'Gris y blanco', 'temperament' => 'Activo', 'birth_date' => '2022-11-20', 'weight' => 22.0, 'sterilized' => true, 'notes' => 'Doble capa, baño especial'],
                ],
            ],
        ];

        foreach ($clientsData as $data) {
            $pets = $data['pets'];
            unset($data['pets']);

            $client = Client::updateOrCreate(
                [
                    'tipo_documento' => $data['tipo_documento'],
                    'numero_documento' => $data['numero_documento'],
                ],
                array_merge($data, ['company_id' => $companyId])
            );

            foreach ($pets as $petData) {
                Pet::updateOrCreate(
                    [
                        'client_id' => $client->id,
                        'company_id' => $companyId,
                        'name' => $petData['name'],
                    ],
                    array_merge($petData, [
                        'client_id' => $client->id,
                        'company_id' => $companyId,
                        'fallecido' => false,
                        'fecha_registro' => $petData['fecha_registro'] ?? $client->fecha_registro ?? $today,
                        'allergies' => isset($petData['allergies']) ? (is_array($petData['allergies']) ? $petData['allergies'] : [$petData['allergies']]) : null,
                        'behavior' => $petData['behavior'] ?? null,
                    ])
                );
            }
        }

        // Recalcular nivel de fidelización según cantidad de mascotas
        Client::where('company_id', $companyId)->get()->each(function (Client $c) {
            $c->recalculateLevel();
        });

        $this->command->info('Clientes y mascotas de prueba creados/actualizados correctamente.');
    }
}
