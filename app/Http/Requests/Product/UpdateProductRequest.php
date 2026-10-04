<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\Concerns\ResolvesRequestCompanyId;
use App\Support\CompanyExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use ResolvesRequestCompanyId;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;
        $companyId = is_object($product) && $product->company_id
            ? (int) $product->company_id
            : $this->requestCompanyId();

        return [
            'category_id' => ['nullable', 'integer', CompanyExists::in('categories', $companyId)],
            'unit_id' => ['nullable', 'integer', CompanyExists::in('units', $companyId)],
            'brand_id' => ['nullable', 'integer', CompanyExists::in('brands', $companyId)],
            'supplier_id' => ['nullable', 'integer', CompanyExists::in('suppliers', $companyId)],
            'area_id' => ['nullable', 'integer', CompanyExists::in('areas', $companyId)],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'code')
                    ->where(fn ($q) => $companyId ? $q->where('company_id', $companyId) : $q->whereRaw('0 = 1'))
                    ->ignore($productId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'item_type' => ['nullable', 'string', 'in:PRODUCTO,SERVICIO'],
            'unit' => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'size:3'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'tax_affection' => ['nullable', 'string', 'max:2'],
            'igv_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
