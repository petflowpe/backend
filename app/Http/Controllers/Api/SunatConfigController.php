<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SunatConfigController extends Controller
{
    private function authorizeCompany(Request $request, Company $company): ?JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }
        if ($user->hasRole('super_admin')) {
            return null;
        }
        if ((int) $user->company_id !== (int) $company->id) {
            return response()->json(['success' => false, 'message' => 'Sin permisos para esta empresa'], 403);
        }

        return null;
    }

    public function show(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->authorizeCompany($request, $company)) {
            return $deny;
        }

        $invoice = $company->getInvoiceConfig();
        $document = $company->getDocumentConfig();

        return response()->json([
            'success' => true,
            'data' => [
                'company' => [
                    'id' => $company->id,
                    'ruc' => $company->ruc,
                    'razon_social' => $company->razon_social,
                    'nombre_comercial' => $company->nombre_comercial,
                    'direccion' => $company->direccion,
                    'ubigeo' => $company->ubigeo,
                    'distrito' => $company->distrito,
                    'provincia' => $company->provincia,
                    'departamento' => $company->departamento,
                    'telefono' => $company->telefono,
                    'email' => $company->email,
                    'web' => $company->web,
                    'usuario_sol' => $company->usuario_sol,
                    'has_clave_sol' => !empty($company->clave_sol),
                    'modo_produccion' => (bool) $company->modo_produccion,
                    'endpoint_beta' => $company->endpoint_beta,
                    'endpoint_produccion' => $company->endpoint_produccion,
                    'has_certificate' => !empty($company->certificado_pem),
                    'has_certificate_password' => !empty($company->certificado_password),
                ],
                'invoice_settings' => array_merge([
                    'series' => [
                        'factura' => 'F001',
                        'boleta' => 'B001',
                        'nota_credito' => 'FC01',
                        'nota_debito' => 'FD01',
                        'guia_remision' => 'T001',
                    ],
                    'ose_provider' => 'sunat',
                    'ose_config' => [],
                    'regimen_tributario' => 'RG',
                    'envio_automatico' => false,
                ], is_array($invoice) ? $invoice : []),
                'document_settings' => is_array($document) ? $document : [],
            ],
        ]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->authorizeCompany($request, $company)) {
            return $deny;
        }

        $validator = Validator::make($request->all(), [
            'company' => 'sometimes|array',
            'company.ruc' => 'sometimes|string|size:11',
            'company.razon_social' => 'sometimes|string|max:255',
            'company.nombre_comercial' => 'nullable|string|max:255',
            'company.direccion' => 'sometimes|string|max:255',
            'company.ubigeo' => 'sometimes|string|size:6',
            'company.distrito' => 'sometimes|string|max:100',
            'company.provincia' => 'sometimes|string|max:100',
            'company.departamento' => 'sometimes|string|max:100',
            'company.telefono' => 'nullable|string|max:20',
            'company.email' => 'nullable|email|max:255',
            'company.web' => 'nullable|url|max:255',
            'company.usuario_sol' => 'nullable|string|max:50',
            'company.clave_sol' => 'nullable|string|max:100',
            'company.certificado_password' => 'nullable|string|max:100',
            'company.endpoint_beta' => 'nullable|url|max:255',
            'company.endpoint_produccion' => 'nullable|url|max:255',
            'invoice_settings' => 'sometimes|array',
            'document_settings' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('company')) {
            $fields = $request->input('company', []);
            if (array_key_exists('clave_sol', $fields) && $fields['clave_sol'] === '') {
                unset($fields['clave_sol']);
            }
            if (array_key_exists('certificado_password', $fields) && $fields['certificado_password'] === '') {
                unset($fields['certificado_password']);
            }
            $fields = array_filter($fields, fn ($v) => $v !== null);
            if (!empty($fields)) {
                $company->update($fields);
            }
        }

        if ($request->has('invoice_settings')) {
            $current = $company->getInvoiceConfig();
            $merged = array_merge(is_array($current) ? $current : [], $request->input('invoice_settings'));
            $company->setConfig('invoice_settings', $merged, null, 'general', 'Config SUNAT');
        }

        if ($request->has('document_settings')) {
            $current = $company->getDocumentConfig();
            $merged = array_merge(is_array($current) ? $current : [], $request->input('document_settings'));
            $company->setConfig('document_settings', $merged, null, 'general', 'Config documentos SUNAT');
        }

        return $this->show($request, $company->fresh());
    }

    public function setEnvironment(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->authorizeCompany($request, $company)) {
            return $deny;
        }

        $validator = Validator::make($request->all(), [
            'modo_produccion' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $company->update(['modo_produccion' => (bool) $request->modo_produccion]);

        Log::info('Ambiente SUNAT actualizado', [
            'company_id' => $company->id,
            'modo' => $company->modo_produccion ? 'produccion' : 'beta',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ambiente actualizado',
            'data' => [
                'modo_produccion' => (bool) $company->modo_produccion,
                'ambiente' => $company->modo_produccion ? 'produccion' : 'beta',
            ],
        ]);
    }

    public function uploadCertificate(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->authorizeCompany($request, $company)) {
            return $deny;
        }

        $validator = Validator::make($request->all(), [
            'certificate_file' => 'required|file|max:4096',
            'certificate_password' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('certificate_file');
        $ext = strtolower($file->getClientOriginalExtension());
        $name = 'cert_' . $company->id . '_' . time() . '.' . ($ext ?: 'pem');
        $path = $file->storeAs('certificado', $name, 'public');

        $update = ['certificado_pem' => $path];
        if ($request->filled('certificate_password')) {
            $update['certificado_password'] = $request->certificate_password;
        }
        $company->update($update);

        return response()->json([
            'success' => true,
            'message' => 'Certificado cargado',
            'data' => ['has_certificate' => true],
        ]);
    }
}
