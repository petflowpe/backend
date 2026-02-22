<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PetConfiguration;

class PetConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        // Especies (requiere migración que cambie type a varchar)
        $species = ['Perro', 'Gato', 'Otro'];
        foreach ($species as $index => $name) {
            PetConfiguration::updateOrCreate(
                ['company_id' => null, 'type' => 'species', 'name' => $name],
                ['sort_order' => $index, 'active' => true]
            );
        }

        // Razas de Perros
        $dogBreeds = [
            'Golden Retriever', 'Labrador', 'Pastor Alemán', 'Bulldog Francés',
            'Poodle', 'Yorkshire Terrier', 'Chihuahua', 'Beagle', 'Boxer',
            'Rottweiler', 'Husky Siberiano', 'Border Collie', 'Cocker Spaniel',
            'Dálmata', 'San Bernardo', 'Schnauzer', 'Shih Tzu', 'Pug',
            'Dachshund', 'Mastín', 'Doberman', 'Mestizo', 'Otro'
        ];

        foreach ($dogBreeds as $index => $breed) {
            PetConfiguration::updateOrCreate(
                ['company_id' => null, 'type' => 'dog_breed', 'name' => $breed],
                ['sort_order' => $index, 'active' => true]
            );
        }

        // Razas de Gatos
        $catBreeds = [
            'Persa', 'Siamés', 'Maine Coon', 'Británico de Pelo Corto',
            'Ragdoll', 'Bengalí', 'Abisinio', 'Scottish Fold',
            'Ruso Azul', 'Sphynx', 'Angora', 'Burmés', 'Mestizo', 'Otro'
        ];

        foreach ($catBreeds as $index => $breed) {
            PetConfiguration::updateOrCreate(
                ['company_id' => null, 'type' => 'cat_breed', 'name' => $breed],
                ['sort_order' => $index, 'active' => true]
            );
        }

        // Temperamentos
        $temperaments = [
            'Agresivo', 'Desconfiado', 'Nervioso', 'Tranquilo',
            'Dócil', 'Juguetón', 'Protector'
        ];

        foreach ($temperaments as $index => $temperament) {
            PetConfiguration::updateOrCreate(
                ['company_id' => null, 'type' => 'temperament', 'name' => $temperament],
                ['sort_order' => $index, 'active' => true]
            );
        }

        // Comportamientos
        $behaviors = [
            'Amigable', 'Mordedor', 'Inquieto', 'Sociable',
            'Tímido', 'Dominante', 'Obediente'
        ];

        foreach ($behaviors as $index => $behavior) {
            PetConfiguration::updateOrCreate(
                ['company_id' => null, 'type' => 'behavior', 'name' => $behavior],
                ['sort_order' => $index, 'active' => true]
            );
        }
    }
}
