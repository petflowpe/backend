<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Route as RouteModel;
use App\Models\RouteStop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class RoutePlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $request->integer('company_id', 1);
            $query = RouteModel::with(['zone', 'vehicle', 'stops.client'])
                ->where('company_id', $companyId)
                ->orderByDesc('date')
                ->orderByDesc('id');

            if ($request->filled('date')) {
                $query->whereDate('date', $request->date);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('zone_id')) {
                $query->where('zone_id', $request->zone_id);
            }

            $perPage = $request->integer('per_page', 20);
            $routes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $routes->items(),
                'meta' => [
                    'total' => $routes->total(),
                    'per_page' => $routes->perPage(),
                    'current_page' => $routes->currentPage(),
                    'last_page' => $routes->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error listar rutas', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
            'zone_id' => 'nullable|integer|exists:zones,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'status' => 'nullable|string|in:planned,in_progress,completed,cancelled',
            'auto_optimize' => 'boolean',
            'stops' => 'nullable|array',
            'stops.*.client_id' => 'required_with:stops|integer|exists:clients,id',
            'stops.*.order' => 'nullable|integer|min:0',
        ]);
        $companyId = (int) ($validated['company_id'] ?? \App\Helpers\ScopeHelper::companyId($request) ?? $request->user()?->company_id);
        if (!$companyId) {
            return response()->json(['message' => 'company_id es requerido o el usuario debe tener empresa asignada.'], 422);
        }
        $route = RouteModel::create([
            'company_id' => $companyId,
            'zone_id' => $validated['zone_id'] ?? null,
            'vehicle_id' => $validated['vehicle_id'] ?? null,
            'name' => $validated['name'],
            'date' => $validated['date'],
            'start_time' => isset($validated['start_time']) ? $validated['start_time'] : null,
            'end_time' => isset($validated['end_time']) ? $validated['end_time'] : null,
            'status' => $validated['status'] ?? 'planned',
            'auto_optimize' => $validated['auto_optimize'] ?? true,
        ]);
        if (!empty($validated['stops'])) {
            foreach ($validated['stops'] as $i => $stop) {
                RouteStop::create([
                    'route_id' => $route->id,
                    'client_id' => $stop['client_id'],
                    'order' => $stop['order'] ?? $i,
                ]);
            }
        }
        $route->load(['zone', 'vehicle', 'stops.client']);
        return response()->json(['success' => true, 'data' => $route], 201);
    }

    public function show(RouteModel $route): JsonResponse
    {
        $route->load(['zone', 'vehicle', 'stops.client']);
        return response()->json(['success' => true, 'data' => $route]);
    }

    public function update(Request $request, RouteModel $route): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'nullable|integer|exists:zones,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'name' => 'sometimes|string|max:255',
            'date' => 'sometimes|date',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'status' => 'nullable|string|in:planned,in_progress,completed,cancelled',
            'auto_optimize' => 'boolean',
            'stops' => 'nullable|array',
            'stops.*.client_id' => 'required_with:stops|integer|exists:clients,id',
            'stops.*.order' => 'nullable|integer|min:0',
        ]);
        $route->update(array_filter([
            'zone_id' => $validated['zone_id'] ?? null,
            'vehicle_id' => $validated['vehicle_id'] ?? null,
            'name' => $validated['name'] ?? null,
            'date' => $validated['date'] ?? null,
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'status' => $validated['status'] ?? null,
            'auto_optimize' => $validated['auto_optimize'] ?? null,
        ], fn ($v) => $v !== null));
        if (array_key_exists('stops', $validated)) {
            $route->stops()->delete();
            foreach ($validated['stops'] ?? [] as $i => $stop) {
                RouteStop::create([
                    'route_id' => $route->id,
                    'client_id' => $stop['client_id'],
                    'order' => $stop['order'] ?? $i,
                ]);
            }
        }
        $route->load(['zone', 'vehicle', 'stops.client']);
        return response()->json(['success' => true, 'data' => $route]);
    }

    public function destroy(RouteModel $route): JsonResponse
    {
        $route->stops()->delete();
        $route->delete();
        return response()->json(['success' => true, 'message' => 'Ruta eliminada']);
    }
}
