<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'companyId' => $this->company_id,
            'zoneId' => $this->zone_id,

            'fullName' => $this->razon_social,
            'commercialName' => $this->nombre_comercial,

            'documentType' => $this->mapDocumentType($this->tipo_documento),
            'documentNumber' => $this->numero_documento,

            'email' => $this->email,
            'phone' => $this->telefono,
            'phone2' => $this->telefono2,

            'address' => $this->direccion,
            'district' => $this->distrito,
            'province' => $this->provincia,
            'department' => $this->departamento,
            'ubigeo' => $this->ubigeo,

            'clientType' => $this->client_type ?? 'Regular',
            'status' => ($this->activo ?? false) ? 'Activo' : 'Inactivo',

            'notes' => $this->notas,

            'petsCount' => isset($this->pets_count) ? (int) $this->pets_count : null,
            'pets' => PetResource::collection($this->whenLoaded('pets')),

            'lastInvoices' => InvoiceSummaryResource::collection($this->whenLoaded('lastInvoices')),
            'nextAppointment' => $this->whenLoaded('nextAppointment', function () {
                return $this->nextAppointment ? new AppointmentSummaryResource($this->nextAppointment) : null;
            }),

            'createdAt' => optional($this->created_at)->toISOString(),
            'updatedAt' => optional($this->updated_at)->toISOString(),
        ];
    }

    private function mapDocumentType(?string $tipo): ?string
    {
        return match ((string) $tipo) {
            '1' => 'DNI',
            '6' => 'RUC',
            '4' => 'CE',
            '7' => 'Pasaporte',
            default => null,
        };
    }
}

