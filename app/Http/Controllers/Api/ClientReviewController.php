<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\ClientReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientReviewController extends Controller
{
    private function resolveCompanyId(Request $request): ?int
    {
        return ScopeHelper::companyId($request)
            ?? ($request->user()?->hasRole('super_admin') && $request->filled('company_id')
                ? (int) $request->company_id
                : null);
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        $query = ClientReview::where('company_id', $companyId)->orderByDesc('created_at');

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        $reviews = $query->limit($request->integer('limit', 100))->get();

        return response()->json(['success' => true, 'data' => $reviews]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        $validator = Validator::make($request->all(), [
            'client_id' => 'nullable|integer|exists:clients,id',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'client_name' => 'required|string|max:255',
            'pet_name' => 'nullable|string|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'service_name' => 'nullable|string|max:255',
            'staff_name' => 'nullable|string|max:255',
            'verified' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $review = ClientReview::create([
            ...$validator->validated(),
            'company_id' => $companyId,
            'verified' => $request->boolean('verified', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reseña registrada',
            'data' => $review,
        ], 201);
    }

    public function respond(Request $request, ClientReview $clientReview): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if ($companyId && (int) $clientReview->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'staff_response' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $clientReview->update(['staff_response' => $request->staff_response]);

        return response()->json([
            'success' => true,
            'message' => 'Respuesta guardada',
            'data' => $clientReview->fresh(),
        ]);
    }
}
