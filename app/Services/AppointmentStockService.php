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
    public const RESERVATION_SOURCE = 'appointment';

    private const CLOSED_STATUSES = ['Cancelada'];

    public function __construct(
        private ProductService $productService,
        private StockReservationService $reservations,
    ) {
    }

    /**
     * Insumos requeridos de un servicio. Acepta services.id o el id del producto espejo SERVICIO.
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

        return $catalogService ? $this->requiredForMirror($catalogService) : [];
    }

    /**
     * Insumos de un producto espejo SERVICIO (de services.required_products si está vinculado).
     *
     * @return array<int, array{product_id?: int, quantity?: float|int}>
     */
    public function requiredForMirror(Product $mirror): array
    {
        if ($mirror->service_id) {
            $fromService = Service::find($mirror->service_id)?->required_products;
            if (is_array($fromService) && count($fromService) > 0) {
                return $fromService;
            }
        }

        $meta = is_array($mirror->metadata) ? $mirror->metadata : [];
        $fromProduct = $meta['required_products'] ?? [];

        return is_array($fromProduct) ? $fromProduct : [];
    }

    /**
     * Cantidades por producto que consume la cita: insumos de cada servicio (× cantidad) e ítems PRODUCTO.
     *
     * @return array{supplies: array<int, float>, items: array<int, float>}
     */
    public function requirements(Appointment $appointment): array
    {
        $appointment->loadMissing('items');
        $supplies = [];
        $items = [];

        $add = function (array &$bucket, $productId, float $qty) {
            $productId = (int) $productId;
            if ($productId <= 0 || $qty <= 0) {
                return;
            }
            $bucket[$productId] = ($bucket[$productId] ?? 0) + $qty;
        };

        $serviceItems = 0;
        foreach ($appointment->items as $item) {
            $type = strtoupper((string) ($item->item_type ?? ''));
            $qty = (float) ($item->quantity ?: 1);

            if (in_array($type, ['PRODUCTO', 'PRODUCT'], true)) {
                $add($items, $item->product_id ?? $item->item_id ?? null, $qty);
                continue;
            }

            if (in_array($type, ['SERVICIO', 'SERVICE'], true) && $item->product_id) {
                $mirror = Product::where('item_type', 'SERVICIO')->find($item->product_id);
                if (! $mirror) {
                    continue;
                }
                $serviceItems++;
                foreach ($this->requiredForMirror($mirror) as $req) {
                    $add($supplies, $req['product_id'] ?? null, (float) ($req['quantity'] ?? 0) * $qty);
                }
            }
        }

        // Citas sin ítems de servicio (formato antiguo): insumos del servicio principal.
        if ($serviceItems === 0 && $appointment->service_id) {
            foreach ($this->resolveRequiredProducts((int) $appointment->service_id) as $req) {
                $add($supplies, $req['product_id'] ?? null, (float) ($req['quantity'] ?? 0));
            }
        }

        return ['supplies' => $supplies, 'items' => $items];
    }

    /**
     * @return array<int, float>
     */
    public function totalRequirements(Appointment $appointment): array
    {
        $req = $this->requirements($appointment);
        $total = $req['supplies'];
        foreach ($req['items'] as $productId => $qty) {
            $total[$productId] = ($total[$productId] ?? 0) + $qty;
        }

        return $total;
    }

    public function alreadyDeducted(Appointment $appointment): bool
    {
        return StockMovement::query()
            ->where('source_id', $appointment->id)
            ->whereIn('source_type', ['appointment', 'appointment_item'])
            ->exists();
    }

    /**
     * Reserva insumos e ítems PRODUCTO de una cita abierta; libera si se cancela y consume al descontarse.
     */
    public function syncReservation(Appointment $appointment): void
    {
        if (in_array($appointment->status, self::CLOSED_STATUSES, true)) {
            $this->reservations->release(self::RESERVATION_SOURCE, $appointment->id);

            return;
        }

        if ($this->alreadyDeducted($appointment)) {
            $this->reservations->consume(self::RESERVATION_SOURCE, $appointment->id);

            return;
        }

        // Facturada pero aún sin descontar: deductOnInvoice usa el almacén reservado y luego la consume.
        if ($appointment->boleta_id || $appointment->invoice_id) {
            return;
        }

        $this->reservations->sync(
            (int) $appointment->company_id,
            self::RESERVATION_SOURCE,
            $appointment->id,
            $this->totalRequirements($appointment),
            $appointment->branch_id ? (int) $appointment->branch_id : null
        );
    }

    /**
     * Descuenta insumos de los servicios e ítems PRODUCTO al emitir comprobante.
     * Idempotente: no vuelve a descontar si ya hay movimiento kardex de la cita.
     */
    public function deductOnInvoice(Appointment $appointment): void
    {
        if ($this->alreadyDeducted($appointment)) {
            $this->reservations->consume(self::RESERVATION_SOURCE, $appointment->id);

            return;
        }

        $this->assertStockAvailable($appointment);
        $req = $this->requirements($appointment);
        $userId = Auth::id();
        $reservedAreas = $this->reservations->activeAreas(self::RESERVATION_SOURCE, $appointment->id);
        $branchId = $appointment->branch_id ? (int) $appointment->branch_id : null;

        $groups = [
            ['rows' => $req['supplies'], 'source' => 'appointment', 'label' => 'insumos'],
            ['rows' => $req['items'], 'source' => 'appointment_item', 'label' => 'producto'],
        ];

        foreach ($groups as $group) {
            foreach ($group['rows'] as $productId => $qty) {
                $product = Product::where('company_id', $appointment->company_id)->find($productId);
                if (! $product || strtoupper((string) $product->item_type) !== 'PRODUCTO') {
                    continue;
                }
                $this->productService->adjustStock(
                    $product,
                    $this->outputArea($product, $qty, $reservedAreas[$product->id] ?? null, $branchId),
                    $qty,
                    'OUT',
                    "Salida por {$group['label']} al facturar cita #{$appointment->id}",
                    [
                        'wrap_transaction' => false,
                        'source_type' => $group['source'],
                        'source_id' => $appointment->id,
                        'branch_id' => $appointment->branch_id,
                        'unit_cost' => (float) ($product->cost_price ?? 0),
                        'created_by' => $userId,
                    ]
                );
            }
        }

        $this->reservations->consume(self::RESERVATION_SOURCE, $appointment->id);
    }

    /**
     * Sale del almacén donde se reservó si aún tiene la cantidad; si no, de un almacén de la sucursal.
     */
    private function outputArea(Product $product, float $qty, ?int $reservedAreaId, ?int $branchId): ?int
    {
        if ($reservedAreaId) {
            $physical = (float) \App\Models\ProductStock::where('product_id', $product->id)
                ->where('area_id', $reservedAreaId)
                ->value('quantity');
            if ($physical + 0.0005 >= $qty) {
                return $reservedAreaId;
            }
        }

        return $this->productService->resolveAreaForBranch($product, $branchId, $qty);
    }

    /**
     * Valida stock físico para facturar (la reserva de la propia cita no resta).
     */
    public function assertStockAvailable(Appointment $appointment): void
    {
        foreach ($this->totalRequirements($appointment) as $productId => $qty) {
            $product = Product::where('company_id', $appointment->company_id)->find($productId);
            if (! $product || strtoupper((string) $product->item_type) !== 'PRODUCTO') {
                continue;
            }
            if ((float) $product->stock + 0.0005 < $qty) {
                throw new Exception(
                    'Stock insuficiente del producto "' . $product->name . '" para facturar (necesario: '
                    . $qty . ', disponible: ' . (float) $product->stock . ').'
                );
            }
        }
    }

    /**
     * Productos sin stock libre suficiente (descontando reservas de otras citas).
     *
     * @param  array<int, float>  $quantities
     * @return array<int, array{product_id: int, name: string, required: float, available: float}>
     */
    public function shortages(int $companyId, array $quantities, ?int $exceptAppointmentId = null): array
    {
        $short = [];
        foreach ($quantities as $productId => $qty) {
            $product = Product::where('company_id', $companyId)->where('item_type', 'PRODUCTO')->find($productId);
            if (! $product) {
                continue;
            }
            $available = $this->reservations->available(
                $product,
                $exceptAppointmentId ? self::RESERVATION_SOURCE : null,
                $exceptAppointmentId
            );
            if ($available + 0.0005 < $qty) {
                $short[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'required' => round($qty, 3),
                    'available' => round(max(0, $available), 3),
                ];
            }
        }

        return $short;
    }
}
