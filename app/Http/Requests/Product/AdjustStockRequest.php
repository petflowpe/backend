<?php

namespace App\Http\Requests\Product;

use App\Support\CompanyExists;
use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) ($this->route('product')?->company_id ?? 0);

        return [
            'area_id' => ['nullable', 'integer', CompanyExists::in('areas', $companyId)],
            'quantity' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'string', 'in:IN,OUT,ADJUST'],
            'notes' => ['nullable', 'string', 'max:500'],
            'batch_number' => ['nullable', 'string', 'max:60'],
            'expiry_date' => ['nullable', 'date'],
            'batch_id' => ['nullable', 'integer'],
        ];
    }
}
