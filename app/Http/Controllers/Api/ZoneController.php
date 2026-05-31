<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ZoneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $request->integer('company_id', 1);
            $query = Zone::where('company_id', $companyId)->orderBy('name');
            if ($request->boolean('only_active', false)) {
                $query->where('active', true);
            }
            $zones = $query->get();
            return response()->json(['success' => true, 'data' => $zones]);
        } catch (Exception $e) {
            Log::error('Error listar zonas', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:20',
            'districts' => 'nullable|array',
            'coverage' => 'nullable|string|max:20',
            'demand' => 'nullable|integer|min:0|max:100',
            'coordinates' => 'nullable|array',
            'active' => 'boolean',
        ]);
        $validated['company_id'] = $validated['company_id'] ?? \App\Helpers\ScopeHelper::companyId($request) ?? $request->user()?->company_id;
        if (empty($validated['company_id'])) {
            return response()->json(['message' => 'company_id es requerido o el usuario debe tener empresa asignada.'], 422);
        }
        $zone = Zone::create($validated);
        return response()->json(['success' => true, 'data' => $zone], 201);
    }

    public function show(Zone $zone): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $zone]);
    }

    public function update(Request $request, Zone $zone): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'color' => 'nullable|string|max:20',
            'districts' => 'nullable|array',
            'coverage' => 'nullable|string|max:20',
            'demand' => 'nullable|integer|min:0|max:100',
            'coordinates' => 'nullable|array',
            'active' => 'boolean',
        ]);
        $zone->update($validated);
        return response()->json(['success' => true, 'data' => $zone->fresh()]);
    }

    public function destroy(Zone $zone): JsonResponse
    {
        $zone->delete();
        return response()->json(['success' => true, 'message' => 'Zona eliminada']);
    }
}
