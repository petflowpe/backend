<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'string', 'in:IN,OUT,ADJUST'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

