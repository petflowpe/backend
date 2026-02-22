<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    public function index(Request $request, Product $product): JsonResponse
    {
        $query = StockMovement::where('product_id', $product->id)
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('date_from') && $request->filled('date_to'), function ($q) use ($request) {
                $q->whereBetween('movement_date', [$request->get('date_from'), $request->get('date_to')]);
            })
            ->orderBy('movement_date')
            ->orderBy('id');

        $movements = $query->get();

        // Calcular saldo acumulado para devolverlo listo al frontend
        $balanceQuantity = 0.0;
        $balanceValue = 0.0;

        $entries = $movements->map(function (StockMovement $m) use (&$balanceQuantity, &$balanceValue) {
            $qty = (float) $m->quantity;
            $totalCost = (float) ($m->total_cost ?? ($m->unit_cost ?? 0) * $qty);

            if (strtoupper($m->type) === 'IN') {
                $balanceQuantity += $qty;
                $balanceValue += $totalCost;
            } elseif (strtoupper($m->type) === 'OUT') {
                $balanceQuantity -= $qty;
                $balanceValue -= $totalCost;
            } else { // ADJUST
                $balanceQuantity += $qty;
                $balanceValue += $totalCost;
            }

            return [
                'id' => $m->id,
                'movement_date' => $m->movement_date,
                'type' => $m->type,
                'quantity' => $qty,
                'unit_cost' => (float) ($m->unit_cost ?? 0),
                'total_cost' => $totalCost,
                'balance' => $balanceQuantity,
                'balance_value' => $balanceValue,
                'source_type' => $m->source_type,
                'source_id' => $m->source_id,
                'notes' => $m->notes,
                'created_by' => $m->user?->name,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
                'entries' => $entries,
                'current_stock' => $balanceQuantity,
                'current_value' => $balanceValue,
            ],
        ]);
    }
}


