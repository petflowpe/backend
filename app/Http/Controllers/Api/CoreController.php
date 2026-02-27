<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints del core: monedas, módulos activos, etc.
 */
class CoreController extends Controller
{
    /**
     * Listar monedas activas (para selectores y formato de montos).
     */
    public function currencies(Request $request): JsonResponse
    {
        $currencies = Currency::active()->orderBy('is_default', 'desc')->orderBy('code')->get();
        return response()->json([
            'success' => true,
            'data' => $currencies,
        ]);
    }

    /**
     * Listar módulos activos (para menú y permisos en frontend).
     */
    public function modules(Request $request): JsonResponse
    {
        $modules = Module::active()->orderBy('order')->get(['id', 'name', 'slug', 'description', 'order']);
        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }
}
