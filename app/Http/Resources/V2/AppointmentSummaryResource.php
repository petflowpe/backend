<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->client_id,
            'petId' => $this->pet_id,
            'date' => optional($this->date)->toDateString(),
            'time' => $this->time,
            'status' => $this->status,
            'serviceName' => $this->service_name,
            'serviceCategory' => $this->service_category,
            'address' => $this->address,
            'district' => $this->district,
            'total' => $this->total,
            'paymentStatus' => $this->payment_status,
        ];
    }
}

