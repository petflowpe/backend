<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Product;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        $q = trim($q);
        if (strlen($q) < 2) {
            return response()->json([
                'success' => true,
                'data' => [
                    'clients' => [],
                    'products' => [],
                    'appointments' => [],
                ],
            ]);
        }
        try {
            $companyId = $request->integer('company_id', 1);
            $search = '%' . $q . '%';

            $clients = Client::where('company_id', $companyId)
                ->where(function ($query) use ($search) {
                    $query->where('razon_social', 'like', $search)
                        ->orWhere('nombre_comercial', 'like', $search)
                        ->orWhere('numero_documento', 'like', $search)
                        ->orWhere('email', 'like', $search);
                })
                ->limit(15)
                ->get(['id', 'razon_social', 'nombre_comercial', 'numero_documento', 'email', 'direccion']);

            $products = Product::where('company_id', $companyId)
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', $search)
                        ->orWhere('code', 'like', $search);
                })
                ->limit(15)
                ->get(['id', 'name', 'code', 'item_type', 'unit_price', 'stock']);

            $appointments = Appointment::with(['client:id,razon_social', 'pet:id,name'])
                ->where('company_id', $companyId)
                ->where(function ($query) use ($search) {
                    $query->whereHas('client', function ($c) use ($search) {
                        $c->where('razon_social', 'like', $search)
                            ->orWhere('nombre_comercial', 'like', $search);
                    })->orWhereHas('pet', function ($p) use ($search) {
                        $p->where('name', 'like', $search);
                    });
                })
                ->limit(10)
                ->get(['id', 'client_id', 'pet_id', 'scheduled_at', 'status', 'total']);

            return response()->json([
                'success' => true,
                'data' => [
                    'clients' => $clients,
                    'products' => $products,
                    'appointments' => $appointments,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error búsqueda global', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
