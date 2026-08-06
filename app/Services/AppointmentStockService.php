<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Product;
use App\Models\Service;
use App\Models\StockMovement;
use Exception;
use Illuminate\Support\Facades\Auth;

class AppointmentStockService
{
    public function __construct(
        private ProductService $productService,
    ) {
    }

    /**
     * Insumos requeridos: tabla services O product SERVICIO (metadata.required_products).
     *
     * @return array<int, array{product_id?: int, quantity?: float|int}>
     */
    public function resolveRequiredProducts(int $serviceId): array
    {
        $fromService = Service::find($serviceId)?->required_products;
        if (is_array($fromService) && count($fromService) > 0) {
            return $fromService;
        }

        $catalogService = Product::query()
            ->where('id', $serviceId)
            ->where('item_type', 'SERVICIO')
            ->first();

        $meta = is_array($catalogService?->metadata) ? $catalogService->metadata : [];
        $fromProduct = $meta['required_products'] ?? [];

        return is_array($fromProduct) ? $fromProduct : [];
    }

    public function alreadyDeducted(Appointment $appointment): bool
    {
        return StockMovement::query()
            ->where('source_id', $appointment->id)
            ->whereIn('source_type', ['appointment', 'appointment_item'])
            ->exists();
    }

    /**
     * Descuenta insumos del servicio e ítems PRODUCTO al emitir comprobante.
     * Idempotente: no vuelve a descontar si ya hay movimiento kardex de la cita.
     */
    public function deductOnInvoice(Appointment $appointment): void
    {
        if ($this->alreadyDeducted($appointment)) {
            return;
        }

        $appointment->loadMissing('items');
        $this->assertStockAvailable($appointment);

        $userId = Auth::id();
        $productService = $this->productService;

        if ($appointment->service_id) {
            foreach ($this->resolveRequiredProducts((int) $appointment->service_id) as $req) {
                $product = Product::find($req['product_id'] ?? null);
                $qty = (float) ($req['quantity'] ?? 0);
                if (! $product || $qty <= 0) {
                    continue;
                }
                $productService->adjustStock(
                    $product,
                    null,
                    $qty,
                    'OUT',
                    'Salida por insumos al facturar cita #' . $appointment->id,
                    [
                        'wrap_transaction' => false,
                        'source_type' => 'appointment',
                        'source_id' => $appointment->id,
                        'branch_id' => $appointment->branch_id,
                        'unit_cost' => (float) ($product->cost_price ?? 0),
                        'created_by' => $userId,
                    ]
                );
            }
        }

        foreach ($appointment->items as $item) {
            $itemType = strtoupper((string) ($item->item_type ?? ''));
            if (! in_array($itemType, ['PRODUCTO', 'PRODUCT'], true)) {
                continue;
            }
            $productId = $item->product_id ?? $item->item_id ?? null;
            if (! $productId) {
                continue;
            }
            $product = Product::find($productId);
            $qty = (float) ($item->quantity ?? 1);
            if (! $product || $qty <= 0) {
                continue;
            }
            $productService->adjustStock(
                $product,
                null,
                $qty,
                'OUT',
                'Salida por producto al facturar cita #' . $appointment->id,
                [
                    'wrap_transaction' => false,
                    'source_type' => 'appointment_item',
                    'source_id' => $appointment->id,
                    'branch_id' => $appointment->branch_id,
                    'unit_cost' => (float) ($product->cost_price ?? 0),
                    'created_by' => $userId,
                ]
            );
        }
    }

    public function assertStockAvailable(Appointment $appointment): void
    {
        $appointment->loadMissing('items');

        if ($appointment->service_id) {
            foreach ($this->resolveRequiredProducts((int) $appointment->service_id) as $req) {
                $product = Product::find($req['product_id'] ?? null);
                $qty = (float) ($req['quantity'] ?? 0);
                if (! $product || $qty <= 0) {
                    continue;
                }
                if ((float) $product->stock < $qty) {
                    throw new Exception(
                        'Stock insuficiente del producto "' . $product->name . '" para facturar (necesario: ' . $qty . ', disponible: ' . $product->stock . ').'
                    );
                }
            }
        }

        foreach ($appointment->items as $item) {
            $itemType = strtoupper((string) ($item->item_type ?? ''));
            if (! in_array($itemType, ['PRODUCTO', 'PRODUCT'], true)) {
                continue;
            }
            $productId = $item->product_id ?? $item->item_id ?? null;
            if (! $productId) {
                continue;
            }
            $product = Product::find($productId);
            $qty = (float) ($item->quantity ?? 1);
            if (! $product || $qty <= 0) {
                continue;
            }
            if ((float) $product->stock < $qty) {
                throw new Exception(
                    'Stock insuficiente del producto "' . $product->name . '" para facturar (necesario: ' . $qty . ', disponible: ' . $product->stock . ').'
                );
            }
        }
    }
}
