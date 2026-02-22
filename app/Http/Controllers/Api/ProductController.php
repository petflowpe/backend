<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\AdjustStockRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}
    /**
     * Listar productos (con filtros opcionales).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'company_id' => $request->integer('company_id'),
                'category_id' => $request->integer('category_id'),
                'area_id' => $request->integer('area_id'),
                'only_active' => $request->boolean('only_active', false),
                'low_stock' => $request->boolean('low_stock', false),
                'search' => $request->get('search'),
                'order_by' => $request->get('order_by', 'name'),
                'order_dir' => $request->get('order_dir', 'asc'),
                'per_page' => $request->integer('per_page', 15),
            ];

            $products = $this->productService->list(array_filter($filters), $filters['per_page']);

            $response = [
                'success' => true,
                'data' => $products,
            ];

            if ($products instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
                $response['pagination'] = [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ];
            }

            return response()->json($response);
        } catch (Exception $e) {
            Log::error('Error al listar productos', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener productos',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Crear producto.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Default values
            $data['item_type'] = $data['item_type'] ?? 'PRODUCTO';
            $data['unit'] = $data['unit'] ?? 'NIU';
            $data['currency'] = $data['currency'] ?? 'PEN';
            $data['tax_affection'] = $data['tax_affection'] ?? '10';
            $data['igv_rate'] = $data['igv_rate'] ?? 18.00;
            $data['active'] = $data['active'] ?? true;

            $product = $this->productService->create($data);

            return response()->json([
                'success' => true,
                'message' => 'Producto creado exitosamente',
                'data' => $product,
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al crear producto', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear producto',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Mostrar producto.
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $product->load('company'),
        ]);
    }

    /**
     * Actualizar producto.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            $data = $request->validated();
            $product = $this->productService->update($product, $data);

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
                'data' => $product,
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar producto', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar producto',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Eliminar (soft) / desactivar producto.
     *
     * Por simplicidad, lo marcamos como inactivo en lugar de borrar físicamente.
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            $product->update(['active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Producto desactivado exitosamente',
            ]);
        } catch (Exception $e) {
            Log::error('Error al desactivar producto', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar producto',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Activar producto explícitamente.
     */
    public function activate(Product $product): JsonResponse
    {
        $product->update(['active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Producto activado exitosamente',
            'data' => $product,
        ]);
    }

    /**
     * Listar productos por empresa (atajo).
     */
    public function getByCompany(int $companyId, Request $request): JsonResponse
    {
        $request->merge(['company_id' => $companyId]);

        return $this->index($request);
    }

    /**
     * Obtener KPIs de productos.
     */
    public function getKPIs(int $companyId): JsonResponse
    {
        try {
            $kpis = $this->productService->getKPIs($companyId);

            return response()->json([
                'success' => true,
                'data' => $kpis,
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener KPIs de productos', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener KPIs',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Obtener productos con stock bajo.
     */
    public function getLowStock(Request $request): JsonResponse
    {
        try {
            $companyId = $request->integer('company_id');
            
            if (!$companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'company_id es requerido',
                ], 400);
            }

            $products = $this->productService->list([
                'company_id' => $companyId,
                'low_stock' => true,
            ]);

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener productos con stock bajo', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener productos con stock bajo',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Ajustar stock de un producto.
     */
    public function adjustStock(AdjustStockRequest $request, Product $product): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $productStock = $this->productService->adjustStock(
                $product,
                $data['area_id'],
                $data['quantity'],
                $data['type'],
                $data['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock ajustado exitosamente',
                'data' => $productStock->load('area'),
            ]);
        } catch (Exception $e) {
            Log::error('Error al ajustar stock', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al ajustar stock',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}


