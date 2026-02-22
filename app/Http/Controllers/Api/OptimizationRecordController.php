<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OptimizationRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OptimizationRecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OptimizationRecord::with('vehicle')
            ->when($request->filled('company_id'), fn($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('vehicle_id'), fn($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->orderByDesc('date');

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'date' => ['required', 'date'],
            'appointments_count' => ['required', 'integer'],
            'original_distance' => ['required', 'numeric'],
            'optimized_distance' => ['required', 'numeric'],
            'distance_saved' => ['required', 'numeric'],
            'time_saved' => ['required', 'numeric'],
            'fuel_saved' => ['required', 'numeric'],
            'efficiency' => ['required', 'numeric'],
            'cost_saved' => ['required', 'numeric'],
            'co2_saved' => ['required', 'numeric'],
        ]);

        $record = OptimizationRecord::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Récord de optimización guardado',
            'data' => $record,
        ], 201);
    }
}
