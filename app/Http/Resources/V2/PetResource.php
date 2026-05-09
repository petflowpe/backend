<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class PetResource extends JsonResource
{
    public function toArray($request): array
    {
        $lastVisit = $this->fecha_ultima_visita
            ?? $this->appointments_max_date
            ?? null;

        return [
            'id' => $this->id,
            'clientId' => $this->client_id,
            'companyId' => $this->company_id,

            'name' => $this->name,
            'species' => $this->normalizeSpecies($this->species),
            'breed' => $this->breed,
            'gender' => $this->gender,
            'birthDate' => optional($this->birth_date)->toDateString(),
            'weight' => $this->weight,

            'medicalNotes' => $this->notes,
            'lastVisit' => $lastVisit ? Carbon::parse($lastVisit)->toDateString() : null,

            // Flag calculado (heurística simple). Puede ajustarse a la lógica exacta del frontend.
            'statusFlag' => $this->statusFlagFromLastVisit($lastVisit),

            'photoUrl' => $this->photo,
            'status' => ($this->fallecido ?? false) ? 'Inactivo' : 'Activo',
        ];
    }

    private function normalizeSpecies(?string $species): ?string
    {
        return match ($species) {
            'Otro' => 'Exótico',
            default => $species,
        };
    }

    private function statusFlagFromLastVisit($lastVisit): ?string
    {
        if (!$lastVisit) {
            return 'Nuevo';
        }

        $days = Carbon::parse($lastVisit)->diffInDays(now());

        if ($days <= 30) {
            return 'Recurrente';
        }
        if ($days <= 180) {
            return 'Recuperado';
        }
        return 'Perdido';
    }
}

