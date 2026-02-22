<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Resumen de ventas por día (y filtros opcionales).
     */
    public function salesSummary(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->selectRaw('DATE(fecha_emision) as date')
            ->selectRaw('company_id, branch_id')
            ->selectRaw('COUNT(*) as documents_count')
            ->selectRaw('SUM(mto_imp_venta) as total_amount')
            ->selectRaw('SUM(mto_oper_gravadas) as total_gravadas')
            ->selectRaw('SUM(mto_oper_exoneradas) as total_exoneradas')
            ->selectRaw('SUM(mto_oper_inafectas) as total_inafectas')
            ->selectRaw('SUM(mto_oper_exportacion) as total_exportacion')
            ->selectRaw('SUM(mto_oper_gratuitas) as total_gratuitas')
            ->selectRaw('SUM(mto_igv) as total_igv')
            ->selectRaw('SUM(mto_ivap) as total_ivap')
            ->selectRaw('SUM(mto_icbper) as total_icbper')
            ->selectRaw('SUM(CASE WHEN estado_sunat = "ACEPTADO" THEN 1 ELSE 0 END) as accepted_count')
            ->selectRaw('SUM(CASE WHEN estado_sunat = "RECHAZADO" THEN 1 ELSE 0 END) as rejected_count')
            ->when($request->filled('company_id'), fn($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('branch_id'), fn($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('date_from') && $request->filled('date_to'), function ($q) use ($request) {
                $q->whereBetween('fecha_emision', [$request->get('date_from'), $request->get('date_to')]);
            })
            ->groupBy(DB::raw('DATE(fecha_emision), company_id, branch_id'))
            ->orderBy('date');

        $data = $query->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
    public function dashboardStats(Request $request): JsonResponse
    {
        $companyId = $request->integer('company_id');
        $branchId = $request->integer('branch_id');

        $stats = [
            'total_sales' => Invoice::where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->where('estado_sunat', 'ACEPTADO')
                ->sum('mto_imp_venta'),
            'appointments_count' => \App\Models\Appointment::where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->whereDate('date', now())
                ->count(),
            'active_clients' => \App\Models\Client::where('company_id', $companyId)
                ->where('activo', true)
                ->count(),
            'total_pets' => \App\Models\Pet::whereHas('client', fn($q) => $q->where('company_id', $companyId))
                ->where('fallecido', false)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function productAnalytics(Request $request): JsonResponse
    {
        $companyId = $request->integer('company_id');

        // Note: This assumes a many-to-many relationship or a polymorphic one between invoices and products via items
        // Since I don't see an InvoiceItem model yet, I'll count from stock movements if available
        $data = \App\Models\StockMovement::where('company_id', $companyId)
            ->where('type', 'OUT')
            ->select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('COUNT(*) as sales_count'))
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function clientAnalytics(Request $request): JsonResponse
    {
        $companyId = $request->integer('company_id');

        $data = \App\Models\Client::where('company_id', $companyId)
            ->withCount('appointments')
            ->withSum('invoices', 'mto_imp_venta')
            ->orderByDesc('appointments_count')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}


