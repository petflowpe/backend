<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'in:RUC,DNI,CE,PASSPORT'],
            'document_number' => ['nullable', 'string', 'max:20'],
            'supplier_type' => ['nullable', 'string', 'in:Mercadería,Servicios,Honorarios,Mixto'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'accounting_account_code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'logo' => ['nullable', 'string', 'max:500'],
            'active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

