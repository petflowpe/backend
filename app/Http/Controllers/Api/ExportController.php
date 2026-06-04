<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExportReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(
        protected ExportReportService $exportService
    ) {}

    public function dataset(Request $request, string $dataset): JsonResponse
    {
        $allowed = [
            'clients', 'pets', 'appointments', 'invoices', 'products',
            'services', 'staff', 'vehicles', 'routes',
        ];

        if (!in_array($dataset, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Dataset no válido',
                'allowed' => $allowed,
            ], 400);
        }

        $rows = $this->exportService->dataset($request, $dataset);

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'dataset' => $dataset,
                'count' => count($rows),
            ],
        ]);
    }

    public function report(Request $request, string $reportId): JsonResponse
    {
        $rows = $this->exportService->report($request, $reportId);

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'report_id' => $reportId,
                'count' => count($rows),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
            ],
        ]);
    }
}
