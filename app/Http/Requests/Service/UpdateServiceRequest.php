<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\Concerns\ResolvesRequestCompanyId;
use App\Support\CompanyExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    use ResolvesRequestCompanyId;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $service = $this->route('service');
        $serviceId = is_object($service) ? $service->id : $service;
        $companyId = is_object($service) && $service->company_id
            ? (int) $service->company_id
            : $this->requestCompanyId();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('services', 'code')
                    ->where(fn ($q) => $companyId ? $q->where('company_id', $companyId) : $q->whereRaw('0 = 1'))
                    ->ignore($serviceId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'],
            'pricing_by_size' => ['nullable', 'boolean'],
            'pricing' => ['nullable', 'array'],
            'breed_exceptions' => ['nullable', 'array'],
            'required_products' => ['nullable', 'array'],
            'required_products.*.product_id' => ['required_with:required_products', 'integer', CompanyExists::in('products', $companyId)],
            'required_products.*.quantity' => ['required_with:required_products', 'numeric', 'min:0.001'],
        ];
    }
}
