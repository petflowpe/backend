<?php

namespace App\Http\Controllers\Api\V2;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Jobs\SubmitBillingSubmissionJob;
use App\Models\BillingArtifact;
use App\Models\BillingDocument;
use App\Models\BillingDocumentLine;
use App\Models\BillingSubmission;
use App\Models\CompanyTaxProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

class BillingDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = ScopeHelper::companyId($request);
            $query = BillingDocument::query()
                ->with(['client:id,razon_social,numero_documento'])
                ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId));

            if ($request->filled('search')) {
                $search = trim((string) $request->query('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhere('number_prefix', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($c) => $c->where('razon_social', 'like', "%{$search}%")
                            ->orWhere('numero_documento', 'like', "%{$search}%"));
                });
            }

            if ($request->filled('statusFiscal')) {
                $query->where('status_fiscal', $request->query('statusFiscal'));
            }

            if ($request->filled('dateFrom')) {
                $query->where('issue_datetime', '>=', $request->query('dateFrom'));
            }
            if ($request->filled('dateTo')) {
                $query->where('issue_datetime', '<=', $request->query('dateTo'));
            }

            $perPage = (int) $request->query('perPage', 15);
            $perPage = max(1, min($perPage, 100));
            $p = $query->orderBy('issue_datetime', 'desc')->paginate($perPage);

            $data = collect($p->items())->map(fn (BillingDocument $d) => $this->docToArray($d));

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'total' => $p->total(),
                    'per_page' => $p->perPage(),
                    'current_page' => $p->currentPage(),
                    'last_page' => $p->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('v2 billing documents index error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al listar documentos'], 500);
        }
    }

    public function show(Request $request, BillingDocument $billingDocument): JsonResponse
    {
        try {
            $companyId = ScopeHelper::companyId($request);
            if ($companyId !== null && (int) $billingDocument->company_id !== (int) $companyId && !$request->user()?->hasRole('super_admin')) {
                return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
            }

            $billingDocument->load([
                'client:id,razon_social,numero_documento,email,telefono',
                'lines',
                'submissions' => fn ($q) => $q->orderBy('id', 'desc'),
                'artifacts' => fn ($q) => $q->orderBy('id', 'desc'),
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->docToArray($billingDocument, true),
            ]);
        } catch (Exception $e) {
            Log::error('v2 billing documents show error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al obtener documento'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'clientId' => 'required|integer|exists:clients,id',
            'branchId' => 'nullable|integer|exists:branches,id',
            'documentType' => 'nullable|string|max:30',
            'issueDatetime' => 'nullable|date',
            'currencyCode' => 'nullable|string|size:3',
            'totals' => 'required|array',
            'totals.subtotal' => 'required|numeric',
            'totals.taxesTotal' => 'required|numeric',
            'totals.total' => 'required|numeric',
            'taxBreakdown' => 'nullable|array',
            'lines' => 'required|array|min:1',
            'lines.*.itemType' => 'required|string|in:product,service',
            'lines.*.productId' => 'nullable|integer',
            'lines.*.description' => 'required|string|max:255',
            'lines.*.qty' => 'required|numeric|min:0.001',
            'lines.*.unitPrice' => 'required|numeric|min:0',
            'lines.*.discount' => 'nullable|numeric|min:0',
            'lines.*.taxes' => 'nullable|array',
            'lines.*.lineTotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $companyId = ScopeHelper::companyId($request);
            if (!$companyId && !$request->user()?->hasRole('super_admin')) {
                return response()->json(['success' => false, 'message' => 'CompanyId no disponible'], 400);
            }

            $data = $validator->validated();

            DB::beginTransaction();

            $doc = BillingDocument::create([
                'company_id' => $companyId,
                'branch_id' => $data['branchId'] ?? null,
                'client_id' => $data['clientId'],
                'document_type' => $data['documentType'] ?? 'invoice',
                'issue_datetime' => $data['issueDatetime'] ?? now(),
                'currency_code' => $data['currencyCode'] ?? 'COP',
                'totals' => $data['totals'],
                'tax_breakdown' => $data['taxBreakdown'] ?? null,
                'payload_snapshot' => [
                    'clientId' => $data['clientId'],
                    'lines' => $data['lines'],
                ],
                'status' => 'issued',
                'status_fiscal' => 'not_sent',
            ]);

            foreach ($data['lines'] as $line) {
                BillingDocumentLine::create([
                    'billing_document_id' => $doc->id,
                    'item_type' => $line['itemType'],
                    'product_id' => $line['productId'] ?? null,
                    'description' => $line['description'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unitPrice'],
                    'discount' => $line['discount'] ?? 0,
                    'taxes' => $line['taxes'] ?? null,
                    'line_total' => $line['lineTotal'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->docToArray($doc->load('lines')),
                'message' => 'Documento creado',
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('v2 billing documents store error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al crear documento'], 500);
        }
    }

    public function submit(Request $request, BillingDocument $billingDocument): JsonResponse
    {
        try {
            $companyId = ScopeHelper::companyId($request);
            if ($companyId !== null && (int) $billingDocument->company_id !== (int) $companyId && !$request->user()?->hasRole('super_admin')) {
                return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
            }

            $profile = CompanyTaxProfile::where('company_id', $billingDocument->company_id)
                ->where('country_code', 'CO')
                ->where('active', true)
                ->first();

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perfil fiscal CO no configurado',
                ], 422);
            }

            if (in_array($billingDocument->status_fiscal, ['queued', 'sent', 'accepted'], true)) {
                return response()->json([
                    'success' => true,
                    'message' => 'El documento ya fue enviado o está en proceso',
                    'data' => [
                        'statusFiscal' => $billingDocument->status_fiscal,
                    ],
                ]);
            }

            $idempotencyKey = (string) Str::uuid();
            $submission = BillingSubmission::create([
                'billing_document_id' => $billingDocument->id,
                'provider_slug' => $profile->provider_slug ?? 'dian_stub',
                'idempotency_key' => $idempotencyKey,
                'request_payload' => [
                    'billingDocumentId' => $billingDocument->id,
                    'companyId' => $billingDocument->company_id,
                    'country' => 'CO',
                    'issueDatetime' => optional($billingDocument->issue_datetime)->toISOString(),
                    'currencyCode' => $billingDocument->currency_code,
                    'totals' => $billingDocument->totals,
                ],
                'status' => 'queued',
            ]);

            $billingDocument->update(['status_fiscal' => 'queued']);
            SubmitBillingSubmissionJob::dispatch($submission->id);

            return response()->json([
                'success' => true,
                'message' => 'Enviado a cola para timbrado/validación',
                'data' => [
                    'submissionId' => $submission->id,
                    'idempotencyKey' => $submission->idempotency_key,
                    'statusFiscal' => 'queued',
                ],
            ], 202);
        } catch (Exception $e) {
            Log::error('v2 billing documents submit error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al enviar documento'], 500);
        }
    }

    public function status(Request $request, BillingDocument $billingDocument): JsonResponse
    {
        try {
            $companyId = ScopeHelper::companyId($request);
            if ($companyId !== null && (int) $billingDocument->company_id !== (int) $companyId && !$request->user()?->hasRole('super_admin')) {
                return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
            }

            $latest = BillingSubmission::where('billing_document_id', $billingDocument->id)
                ->orderBy('id', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'statusFiscal' => $billingDocument->status_fiscal,
                    'latestSubmission' => $latest ? [
                        'id' => $latest->id,
                        'status' => $latest->status,
                        'externalId' => $latest->external_id,
                        'acceptedAt' => optional($latest->accepted_at)->toISOString(),
                        'lastCheckedAt' => optional($latest->last_checked_at)->toISOString(),
                        'errorCode' => $latest->error_code,
                        'errorMessage' => $latest->error_message,
                    ] : null,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('v2 billing documents status error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al obtener estado'], 500);
        }
    }

    public function artifact(Request $request, BillingDocument $billingDocument, string $type): JsonResponse
    {
        try {
            $companyId = ScopeHelper::companyId($request);
            if ($companyId !== null && (int) $billingDocument->company_id !== (int) $companyId && !$request->user()?->hasRole('super_admin')) {
                return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
            }

            $artifact = BillingArtifact::where('billing_document_id', $billingDocument->id)
                ->where('type', $type)
                ->orderBy('id', 'desc')
                ->first();

            if (!$artifact) {
                return response()->json(['success' => false, 'message' => 'Artifact no encontrado'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'type' => $artifact->type,
                    'path' => $artifact->path,
                    'hash' => $artifact->hash,
                    'meta' => $artifact->meta,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('v2 billing documents artifact error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al obtener artifact'], 500);
        }
    }

    private function docToArray(BillingDocument $d, bool $includeRelations = false): array
    {
        $base = [
            'id' => $d->id,
            'companyId' => $d->company_id,
            'branchId' => $d->branch_id,
            'clientId' => $d->client_id,
            'documentType' => $d->document_type,
            'issueDatetime' => optional($d->issue_datetime)->toISOString(),
            'currencyCode' => $d->currency_code,
            'numberPrefix' => $d->number_prefix,
            'number' => $d->number,
            'totals' => $d->totals,
            'taxBreakdown' => $d->tax_breakdown,
            'status' => $d->status,
            'statusFiscal' => $d->status_fiscal,
            'createdAt' => optional($d->created_at)->toISOString(),
            'updatedAt' => optional($d->updated_at)->toISOString(),
        ];

        if (!$includeRelations) {
            return $base;
        }

        return array_merge($base, [
            'client' => $d->relationLoaded('client') && $d->client ? [
                'id' => $d->client->id,
                'fullName' => $d->client->razon_social,
                'documentNumber' => $d->client->numero_documento,
                'email' => $d->client->email,
                'phone' => $d->client->telefono,
            ] : null,
            'lines' => $d->relationLoaded('lines') ? $d->lines->map(fn ($l) => [
                'id' => $l->id,
                'itemType' => $l->item_type,
                'productId' => $l->product_id,
                'description' => $l->description,
                'qty' => (float) $l->qty,
                'unitPrice' => (float) $l->unit_price,
                'discount' => (float) $l->discount,
                'taxes' => $l->taxes,
                'lineTotal' => (float) $l->line_total,
            ])->values() : [],
            'submissions' => $d->relationLoaded('submissions') ? $d->submissions->map(fn ($s) => [
                'id' => $s->id,
                'providerSlug' => $s->provider_slug,
                'status' => $s->status,
                'externalId' => $s->external_id,
                'acceptedAt' => optional($s->accepted_at)->toISOString(),
                'lastCheckedAt' => optional($s->last_checked_at)->toISOString(),
                'errorCode' => $s->error_code,
                'errorMessage' => $s->error_message,
            ])->values() : [],
            'artifacts' => $d->relationLoaded('artifacts') ? $d->artifacts->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'path' => $a->path,
                'hash' => $a->hash,
                'meta' => $a->meta,
            ])->values() : [],
        ]);
    }
}

