<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\Concerns\ResolvesRequestCompanyId;
use App\Support\CompanyExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    use ResolvesRequestCompanyId;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->requestCompanyId();

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'category_id' => ['nullable', 'integer', CompanyExists::in('categories', $companyId)],
            'unit_id' => ['nullable', 'integer', CompanyExists::in('units', $companyId)],
            'brand_id' => ['nullable', 'integer', CompanyExists::in('brands', $companyId)],
            'supplier_id' => ['nullable', 'integer', CompanyExists::in('suppliers', $companyId)],
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'item_type' => ['nullable', 'string', 'in:PRODUCTO,SERVICIO'],
            'unit' => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'size:3'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'tax_affection' => ['nullable', 'string', 'max:2'],
            'igv_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'area_id' => ['nullable', 'integer', CompanyExists::in('areas', $companyId)],
            'active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
