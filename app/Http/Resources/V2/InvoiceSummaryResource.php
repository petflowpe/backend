<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'serie' => $this->serie,
            'number' => $this->correlativo,
            'numberFull' => $this->numero_completo,
            'issueDate' => optional($this->fecha_emision)->toDateString(),
            'currency' => $this->moneda,
            'total' => $this->mto_imp_venta,
            'sunatStatus' => $this->estado_sunat,
        ];
    }
}

