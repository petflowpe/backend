<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\VehicleInspectionAttachment;
use App\Models\VehicleInspectionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VehicleInspectionController extends Controller
{
    private const DEFAULT_TEMPLATE = [
        'name' => 'Inspección vehicular - movilidad canina',
        'categories' => [
            ['name' => 'Documentos', 'items' => ['Licencia', 'SOAT', 'Tarjeta de propiedad']],
            ['name' => 'Seguridad', 'items' => ['Llanta de repuesto', 'Extintor', 'Botiquín', 'Triángulo de seguridad']],
            ['name' => 'Parte externa', 'items' => ['Parabrisas', 'Espejo retrovisor', 'Luces delanteras', 'Luces traseras', 'Carrocería']],
            ['name' => 'Parte interna', 'items' => ['Orden y limpieza', 'Caniles en buen estado', 'Ventilación', 'Cinturones y seguros']],
            ['name' => 'Movilidad canina', 'items' => ['Jaulas/caniles asegurados', 'Higiene del área de mascotas', 'Agua y kit de limpieza']],
        ],
    ];

    private function companyId(Request $request): ?int
    {
        return ScopeHelper::companyId($request);
    }

    private function assertVehicleScope(Request $request, Vehicle $vehicle): void
    {
        $companyId = $this->companyId($request);
        if ($companyId && (int) $vehicle->company_id !== (int) $companyId) {
            abort(403, 'No autorizado');
        }
    }

    private function templateQuery(Request $request)
    {
        $companyId = $this->companyId($request);

        return VehicleInspectionTemplate::query()
            ->with(['categories.items'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('active', true)
            ->orderByDesc('id');
    }

    public function templates(Request $request): JsonResponse
    {
        $templates = $this->templateQuery($request)->get();

        if ($templates->isEmpty()) {
            $templates = collect([$this->ensureDefaultTemplate($request)->load('categories.items')]);
        }

        return response()->json([
            'success' => true,
            'data' => $templates->map(fn (VehicleInspectionTemplate $template) => $this->templateToArray($template))->values(),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:vehicle_inspection_templates,id',
            'name' => 'required|string|max:160',
            'vehicle_type' => 'nullable|string|max:80',
            'categories' => 'required|array|min:1',
            'categories.*.id' => 'nullable|integer',
            'categories.*.name' => 'required|string|max:160',
            'categories.*.items' => 'required|array|min:1',
            'categories.*.items.*.id' => 'nullable|integer',
            'categories.*.items.*.label' => 'required|string|max:180',
            'categories.*.items.*.required' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $companyId = $this->companyId($request);
        $template = DB::transaction(function () use ($request, $data, $companyId) {
            $template = ! empty($data['id'])
                ? VehicleInspectionTemplate::query()->where('company_id', $companyId)->findOrFail($data['id'])
                : new VehicleInspectionTemplate(['company_id' => $companyId, 'created_by' => $request->user()?->id]);

            $template->fill([
                'name' => trim($data['name']),
                'vehicle_type' => $data['vehicle_type'] ?? null,
                'active' => true,
            ]);
            $template->save();

            $template->categories()->delete();

            foreach ($data['categories'] as $categoryIndex => $categoryData) {
                $category = $template->categories()->create([
                    'name' => trim($categoryData['name']),
                    'sort_order' => $categoryIndex,
                ]);
                foreach ($categoryData['items'] as $itemIndex => $itemData) {
                    $category->items()->create([
                        'label' => trim($itemData['label']),
                        'required' => (bool) ($itemData['required'] ?? true),
                        'sort_order' => $itemIndex,
                    ]);
                }
            }

            return $template->load('categories.items');
        });

        return response()->json([
            'success' => true,
            'message' => 'Plantilla guardada',
            'data' => $this->templateToArray($template),
        ], 201);
    }

    public function restoreTemplate(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        if ($companyId) {
            VehicleInspectionTemplate::where('company_id', $companyId)->update(['active' => false]);
        }

        $template = $this->createTemplateFromPayload($request, self::DEFAULT_TEMPLATE);

        return response()->json([
            'success' => true,
            'message' => 'Plantilla restaurada',
            'data' => $this->templateToArray($template->load('categories.items')),
        ], 201);
    }

    public function list(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $items = VehicleInspection::query()
            ->with(['vehicle:id,name,placa,marca,modelo', 'results', 'attachments'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->orderByDesc('inspected_at')
            ->paginate($request->integer('per_page', 200));

        return response()->json([
            'success' => true,
            'data' => collect($items->items())->map(fn (VehicleInspection $inspection) => $this->inspectionToArray($inspection))->values(),
            'meta' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->assertVehicleScope($request, $vehicle);

        $validator = Validator::make($request->all(), [
            'template_id' => 'nullable|integer|exists:vehicle_inspection_templates,id',
            'inspected_at' => 'required|date',
            'odometer' => 'nullable|integer|min:0',
            'driver_name' => 'required|string|max:160',
            'supervisor_name' => 'nullable|string|max:160',
            'observations' => 'nullable|string',
            'driver_signature' => 'required|string',
            'supervisor_signature' => 'required|string',
            'results' => 'required|array|min:1',
            'results.*.template_item_id' => 'nullable|integer|exists:vehicle_inspection_template_items,id',
            'results.*.category_name' => 'required|string|max:160',
            'results.*.item_label' => 'required|string|max:180',
            'results.*.passed' => 'required|boolean',
            'results.*.notes' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required_with:attachments|string|max:180',
            'attachments.*.mime_type' => 'required_with:attachments|string|in:image/jpeg,image/png,image/webp,image/gif,application/pdf',
            'attachments.*.size' => 'required_with:attachments|integer|min:1|max:1887437',
            'attachments.*.data_url' => 'required_with:attachments|string',
        ]);

        $validator->after(function ($validator) use ($request) {
            foreach (['driver_signature', 'supervisor_signature'] as $field) {
                $value = (string) $request->input($field, '');
                if (! str_starts_with($value, 'data:image/png;base64,')) {
                    $validator->errors()->add($field, 'La firma debe ser una imagen PNG en data URL.');
                }
            }

            foreach ($request->input('attachments', []) as $index => $attachment) {
                $dataUrl = (string) ($attachment['data_url'] ?? '');
                $mimeType = (string) ($attachment['mime_type'] ?? '');
                if (! str_starts_with($dataUrl, "data:{$mimeType};base64,")) {
                    $validator->errors()->add("attachments.{$index}.data_url", 'El adjunto debe enviarse como data URL/base64 válida.');
                    continue;
                }

                $size = $this->dataUrlDecodedSize($dataUrl);
                if ($size <= 0 || $size > 1887437) {
                    $validator->errors()->add("attachments.{$index}.data_url", 'Cada adjunto debe pesar máximo 1.8 MB.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $results = $data['results'];
        $total = max(count($results), 1);
        $passed = collect($results)->filter(fn ($row) => (bool) $row['passed'])->count();
        $compliance = round(($passed / $total) * 100, 1);
        $status = $compliance >= 90 ? 'approved' : ($compliance >= 70 ? 'attention_required' : 'rejected');

        $inspection = DB::transaction(function () use ($request, $vehicle, $data, $results, $compliance, $status) {
            $previousDriverScore = (int) ($vehicle->indice_chofer ?? 100);
            $newDriverScore = (int) round(($previousDriverScore * 0.35) + ($compliance * 0.65));
            $observationPoints = $compliance < 85
                ? max(1, (int) ceil((85 - $compliance) / 5))
                : 0;

            $inspection = VehicleInspection::create([
                'company_id' => $this->companyId($request) ?? $vehicle->company_id,
                'vehicle_id' => $vehicle->id,
                'template_id' => $data['template_id'] ?? null,
                'inspected_at' => $data['inspected_at'],
                'odometer' => $data['odometer'] ?? null,
                'driver_name' => trim($data['driver_name']),
                'supervisor_name' => $data['supervisor_name'] ?? null,
                'compliance_percent' => $compliance,
                'status' => $status,
                'observations' => $data['observations'] ?? null,
                'driver_signature' => $data['driver_signature'] ?? null,
                'supervisor_signature' => $data['supervisor_signature'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($results as $index => $row) {
                $inspection->results()->create([
                    'template_item_id' => $row['template_item_id'] ?? null,
                    'category_name' => trim($row['category_name']),
                    'item_label' => trim($row['item_label']),
                    'passed' => (bool) $row['passed'],
                    'notes' => $row['notes'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            foreach (($data['attachments'] ?? []) as $attachment) {
                $attachmentPayload = [
                    'inspection_id' => $inspection->id,
                    'path' => 'data-url',
                    'original_name' => $attachment['name'],
                    'mime_type' => $attachment['mime_type'],
                    'size' => $attachment['size'],
                ];
                if (Schema::hasColumn('vehicle_inspection_attachments', 'data_url')) {
                    $attachmentPayload['data_url'] = $attachment['data_url'];
                }
                VehicleInspectionAttachment::create($attachmentPayload);
            }

            $vehicleUpdates = [];
            if (Schema::hasColumn('vehicles', 'ultimo_cumplimiento_inspeccion')) {
                $vehicleUpdates['ultimo_cumplimiento_inspeccion'] = $compliance;
            }
            if (Schema::hasColumn('vehicles', 'fecha_ultima_inspeccion')) {
                $vehicleUpdates['fecha_ultima_inspeccion'] = $data['inspected_at'];
            }
            if (Schema::hasColumn('vehicles', 'indice_chofer')) {
                $vehicleUpdates['indice_chofer'] = max(0, min(100, $newDriverScore));
            }
            if (Schema::hasColumn('vehicles', 'puntos_observacion_chofer')) {
                $vehicleUpdates['puntos_observacion_chofer'] = (int) ($vehicle->puntos_observacion_chofer ?? 0) + $observationPoints;
            }

            if (! empty($data['observations']) && Schema::hasColumn('vehicles', 'observaciones_inspeccion_acumuladas')) {
                $previousNotes = trim((string) ($vehicle->observaciones_inspeccion_acumuladas ?? ''));
                $nextNote = '[' . now()->format('Y-m-d H:i') . '] ' . trim($data['observations']);
                $vehicleUpdates['observaciones_inspeccion_acumuladas'] = trim($previousNotes . PHP_EOL . $nextNote);
            }

            if (! empty($data['odometer'])) {
                $vehicleUpdates['kilometraje'] = max((int) $data['odometer'], (int) ($vehicle->kilometraje ?? 0));
            }

            if (! empty($vehicleUpdates)) {
                $vehicle->update($vehicleUpdates);
            }

            return $inspection->load(['vehicle:id,name,placa,marca,modelo', 'results', 'attachments']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Inspección guardada',
            'data' => $this->inspectionToArray($inspection),
        ], 201);
    }

    public function show(Request $request, VehicleInspection $vehicleInspection): JsonResponse
    {
        $companyId = $this->companyId($request);
        if ($companyId && (int) $vehicleInspection->company_id !== (int) $companyId) {
            abort(403, 'No autorizado');
        }

        return response()->json([
            'success' => true,
            'data' => $this->inspectionToArray($vehicleInspection->load(['vehicle:id,name,placa,marca,modelo', 'results', 'attachments'])),
        ]);
    }

    public function destroy(Request $request, VehicleInspection $vehicleInspection): JsonResponse
    {
        $companyId = $this->companyId($request);
        if ($companyId && (int) $vehicleInspection->company_id !== (int) $companyId) {
            abort(403, 'No autorizado');
        }

        foreach ($vehicleInspection->attachments as $attachment) {
            if (! str_starts_with((string) $attachment->url, 'data:')) {
                Storage::disk('public')->delete($attachment->path);
            }
        }
        $vehicleInspection->delete();

        return response()->json(['success' => true, 'message' => 'Inspección eliminada']);
    }

    private function ensureDefaultTemplate(Request $request): VehicleInspectionTemplate
    {
        $companyId = $this->companyId($request);
        $existing = VehicleInspectionTemplate::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->with('categories.items')
            ->first();

        return $existing ?: $this->createTemplateFromPayload($request, self::DEFAULT_TEMPLATE);
    }

    private function createTemplateFromPayload(Request $request, array $payload): VehicleInspectionTemplate
    {
        return DB::transaction(function () use ($request, $payload) {
            $template = VehicleInspectionTemplate::create([
                'company_id' => $this->companyId($request),
                'name' => $payload['name'],
                'vehicle_type' => null,
                'active' => true,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($payload['categories'] as $categoryIndex => $categoryData) {
                $category = $template->categories()->create([
                    'name' => $categoryData['name'],
                    'sort_order' => $categoryIndex,
                ]);

                foreach ($categoryData['items'] as $itemIndex => $label) {
                    $category->items()->create([
                        'label' => $label,
                        'required' => true,
                        'sort_order' => $itemIndex,
                    ]);
                }
            }

            return $template;
        });
    }

    private function templateToArray(VehicleInspectionTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'vehicle_type' => $template->vehicle_type,
            'active' => $template->active,
            'categories' => $template->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'items' => $category->items->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $item->label,
                    'required' => $item->required,
                    'sort_order' => $item->sort_order,
                ])->values(),
            ])->values(),
        ];
    }

    private function inspectionToArray(VehicleInspection $inspection): array
    {
        return [
            'id' => $inspection->id,
            'vehicle_id' => $inspection->vehicle_id,
            'vehicle' => $inspection->vehicle,
            'template_id' => $inspection->template_id,
            'inspected_at' => $inspection->inspected_at?->toIso8601String(),
            'odometer' => $inspection->odometer,
            'driver_name' => $inspection->driver_name,
            'supervisor_name' => $inspection->supervisor_name,
            'compliance_percent' => (float) $inspection->compliance_percent,
            'status' => $inspection->status,
            'observations' => $inspection->observations,
            'driver_signature' => $inspection->driver_signature,
            'supervisor_signature' => $inspection->supervisor_signature,
            'results' => $inspection->results->map(fn ($result) => [
                'id' => $result->id,
                'template_item_id' => $result->template_item_id,
                'category_name' => $result->category_name,
                'item_label' => $result->item_label,
                'passed' => $result->passed,
                'notes' => $result->notes,
            ])->values(),
            'attachments' => $inspection->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'url' => $attachment->url,
                'data_url' => Schema::hasColumn('vehicle_inspection_attachments', 'data_url') ? $attachment->data_url : null,
            ])->values(),
        ];
    }

    private function dataUrlDecodedSize(string $dataUrl): int
    {
        $commaPosition = strpos($dataUrl, ',');
        if ($commaPosition === false) {
            return 0;
        }

        $base64 = substr($dataUrl, $commaPosition + 1);
        $decoded = base64_decode($base64, true);

        return $decoded === false ? 0 : strlen($decoded);
    }
}
