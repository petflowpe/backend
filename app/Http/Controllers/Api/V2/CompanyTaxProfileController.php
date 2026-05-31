<?php

namespace App\Http\Controllers\Api\V2;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\CompanyTaxProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyTaxProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CompanyTaxProfile::class);

        try {
            $companyId = ScopeHelper::companyId($request);

            $query = CompanyTaxProfile::query()
                ->orderBy('country_code')
                ->orderBy('id', 'desc');

            if (!$request->user()?->hasRole('super_admin')) {
                $query->where('company_id', $companyId);
            } elseif ($request->filled('companyId')) {
                $query->where('company_id', (int) $request->input('companyId'));
            }

            $data = $query->get()->map(fn (CompanyTaxProfile $p) => $this->toArray($p));

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('v2 tax profiles index error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al listar perfiles fiscales'], 500);
        }
    }

    public function show(CompanyTaxProfile $companyTaxProfile): JsonResponse
    {
        $this->authorize('view', $companyTaxProfile);

        return response()->json([
            'success' => true,
            'data' => $this->toArray($companyTaxProfile),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CompanyTaxProfile::class);

        $validator = Validator::make($request->all(), [
            'companyId' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'countryCode' => ['required', 'string', 'size:2'],
            'taxId' => ['required', 'string', 'max:30'],
            'taxIdDv' => ['nullable', 'string', 'max:5'],
            'legalName' => ['required', 'string', 'max:255'],
            'tradeName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'addressLine' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postalCode' => ['nullable', 'string', 'max:20'],
            'currencyCodeDefault' => ['nullable', 'string', 'size:3'],
            'localeDefault' => ['nullable', 'string', 'max:10'],
            'environment' => ['nullable', 'string', Rule::in(['test', 'prod'])],
            'providerSlug' => ['nullable', 'string', 'max:50'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            $companyId = ScopeHelper::companyId($request);

            if ($request->user()?->hasRole('super_admin') && !empty($data['companyId'])) {
                $companyId = (int) $data['companyId'];
            }

            $profile = CompanyTaxProfile::create([
                'company_id' => $companyId,
                'country_code' => strtoupper($data['countryCode']),
                'tax_id' => $data['taxId'],
                'tax_id_dv' => $data['taxIdDv'] ?? null,
                'legal_name' => $data['legalName'],
                'trade_name' => $data['tradeName'] ?? null,
                'email' => $data['email'] ?? null,
                'address_line' => $data['addressLine'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postalCode'] ?? null,
                'currency_code_default' => $data['currencyCodeDefault'] ?? 'COP',
                'locale_default' => $data['localeDefault'] ?? 'es-CO',
                'environment' => $data['environment'] ?? 'test',
                'provider_slug' => $data['providerSlug'] ?? 'dian_stub',
                'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->toArray($profile),
                'message' => 'Perfil fiscal creado',
            ], 201);
        } catch (\Throwable $e) {
            Log::error('v2 tax profiles store error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al crear perfil fiscal'], 500);
        }
    }

    public function update(Request $request, CompanyTaxProfile $companyTaxProfile): JsonResponse
    {
        $this->authorize('update', $companyTaxProfile);

        $validator = Validator::make($request->all(), [
            'taxId' => ['sometimes', 'required', 'string', 'max:30'],
            'taxIdDv' => ['nullable', 'string', 'max:5'],
            'legalName' => ['sometimes', 'required', 'string', 'max:255'],
            'tradeName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'addressLine' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postalCode' => ['nullable', 'string', 'max:20'],
            'currencyCodeDefault' => ['nullable', 'string', 'size:3'],
            'localeDefault' => ['nullable', 'string', 'max:10'],
            'environment' => ['nullable', 'string', Rule::in(['test', 'prod'])],
            'providerSlug' => ['nullable', 'string', 'max:50'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $update = [];

        $map = [
            'taxId' => 'tax_id',
            'taxIdDv' => 'tax_id_dv',
            'legalName' => 'legal_name',
            'tradeName' => 'trade_name',
            'email' => 'email',
            'addressLine' => 'address_line',
            'city' => 'city',
            'state' => 'state',
            'postalCode' => 'postal_code',
            'currencyCodeDefault' => 'currency_code_default',
            'localeDefault' => 'locale_default',
            'environment' => 'environment',
            'providerSlug' => 'provider_slug',
            'active' => 'active',
        ];
        foreach ($map as $in => $out) {
            if (array_key_exists($in, $data)) {
                $update[$out] = $data[$in];
            }
        }

        $companyTaxProfile->update($update);

        return response()->json([
            'success' => true,
            'data' => $this->toArray($companyTaxProfile->fresh()),
            'message' => 'Perfil fiscal actualizado',
        ]);
    }

    public function destroy(CompanyTaxProfile $companyTaxProfile): JsonResponse
    {
        $this->authorize('delete', $companyTaxProfile);

        $companyTaxProfile->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perfil fiscal eliminado',
        ]);
    }

    private function toArray(CompanyTaxProfile $p): array
    {
        return [
            'id' => $p->id,
            'companyId' => $p->company_id,
            'countryCode' => $p->country_code,
            'taxId' => $p->tax_id,
            'taxIdDv' => $p->tax_id_dv,
            'legalName' => $p->legal_name,
            'tradeName' => $p->trade_name,
            'email' => $p->email,
            'addressLine' => $p->address_line,
            'city' => $p->city,
            'state' => $p->state,
            'postalCode' => $p->postal_code,
            'currencyCodeDefault' => $p->currency_code_default,
            'localeDefault' => $p->locale_default,
            'environment' => $p->environment,
            'providerSlug' => $p->provider_slug,
            'active' => (bool) $p->active,
            'createdAt' => optional($p->created_at)->toISOString(),
            'updatedAt' => optional($p->updated_at)->toISOString(),
        ];
    }
}

