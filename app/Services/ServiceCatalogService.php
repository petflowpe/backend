<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ServiceCatalogService
{
    /**
     * @return Collection<int, Service>
     */
    public function list(int $companyId, bool $onlyActive = false, ?string $search = null): Collection
    {
        $query = Service::query()
            ->where('company_id', $companyId)
            ->orderBy('category')
            ->orderBy('name');

        if ($onlyActive) {
            $query->where('active', true);
        }

        if ($search) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('category', 'like', "%{$term}%");
            });
        }

        return $query->get();
    }

    public function create(array $data): Service
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['code'])) {
                $data['code'] = $this->generateCode(
                    (int) $data['company_id'],
                    (string) ($data['name'] ?? 'SRV'),
                    (string) ($data['area'] ?? '')
                );
            }

            $service = Service::create($data);
            $this->syncProductMirror($service);

            return $service->fresh();
        });
    }

    public function update(Service $service, array $data): Service
    {
        return DB::transaction(function () use ($service, $data) {
            $service->update($data);
            $this->syncProductMirror($service->fresh());

            return $service->fresh();
        });
    }

    public function delete(Service $service): bool
    {
        return DB::transaction(function () use ($service) {
            Product::query()
                ->where('service_id', $service->id)
                ->where('item_type', 'SERVICIO')
                ->update(['active' => false]);

            return (bool) $service->update(['active' => false]);
        });
    }

    public function activate(Service $service): Service
    {
        return DB::transaction(function () use ($service) {
            $service->update(['active' => true]);
            Product::query()
                ->where('service_id', $service->id)
                ->where('item_type', 'SERVICIO')
                ->update(['active' => true]);

            return $service->fresh();
        });
    }

    /**
     * Mirror en products (item_type=SERVICIO) para facturación/diálogos legacy.
     * El ID canónico de citas/portal sigue siendo services.id.
     */
    public function syncProductMirror(Service $service): Product
    {
        $pricing = is_array($service->pricing) ? $service->pricing : [];
        $medium = $pricing['medium'] ?? (is_array($pricing) ? reset($pricing) : null) ?: [];
        $unitPrice = (float) ($medium['price'] ?? 0);
        $costPrice = (float) ($medium['cost'] ?? 0);
        $duration = (int) ($medium['duration'] ?? 45);

        $payload = [
            'company_id' => $service->company_id,
            'service_id' => $service->id,
            'name' => $service->name,
            'code' => $service->code,
            'description' => $service->description,
            'item_type' => 'SERVICIO',
            'unit' => 'ZZ',
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
            'stock' => 0,
            'active' => (bool) $service->active,
            'metadata' => [
                'pricing' => $pricing,
                'pricingBySize' => (bool) $service->pricing_by_size,
                'breedExceptions' => $service->breed_exceptions ?? [],
                'required_products' => $service->required_products ?? [],
                'duration' => $duration,
                'area' => $service->area,
                'category' => $service->category,
            ],
        ];

        $existing = Product::query()
            ->where('company_id', $service->company_id)
            ->where(function ($q) use ($service) {
                $q->where('service_id', $service->id)
                    ->orWhere(function ($q2) use ($service) {
                        $q2->where('item_type', 'SERVICIO')
                            ->where('code', $service->code);
                    });
            })
            ->first();

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return Product::create($payload);
    }

    public function generateCode(int $companyId, string $name, string $area = ''): string
    {
        $areaPrefix = strtoupper(substr(preg_replace('/\s+/', '', $area) ?: 'SV', 0, 2));
        $namePrefix = strtoupper(substr(preg_replace('/\s+/', '', $name) ?: 'SRV', 0, 3));
        $base = "{$areaPrefix}-{$namePrefix}";

        $counter = 1;
        do {
            $code = sprintf('%s-%03d', $base, $counter);
            $exists = Service::query()
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->exists();
            $counter++;
        } while ($exists && $counter < 1000);

        return $code;
    }

    /**
     * Precio/duración “representativos” desde pricing por tamaño.
     *
     * @return array{price: float, cost: float, duration: int}
     */
    public static function representativePricing(?array $pricing): array
    {
        if (! is_array($pricing) || $pricing === []) {
            return ['price' => 0.0, 'cost' => 0.0, 'duration' => 45];
        }

        $preferred = $pricing['medium'] ?? null;
        if (! is_array($preferred)) {
            $first = reset($pricing);
            $preferred = is_array($first) ? $first : [];
        }

        return [
            'price' => (float) ($preferred['price'] ?? 0),
            'cost' => (float) ($preferred['cost'] ?? 0),
            'duration' => (int) ($preferred['duration'] ?? 45),
        ];
    }

    /**
     * Resuelve services.id canónico desde un id que puede ser service o product-mirror.
     */
    public function resolveCanonicalServiceId(?int $id): ?int
    {
        if (! $id) {
            return null;
        }

        if (Service::query()->whereKey($id)->exists()) {
            return $id;
        }

        $mirror = Product::query()
            ->whereKey($id)
            ->where('item_type', 'SERVICIO')
            ->first();

        return $mirror?->service_id ? (int) $mirror->service_id : null;
    }

    /**
     * Producto espejo (SERVICIO) para facturación, a partir de services.id o products.id.
     */
    public function resolveProductMirrorId(?int $id): ?int
    {
        if (! $id) {
            return null;
        }

        $byService = Product::query()
            ->where('service_id', $id)
            ->where('item_type', 'SERVICIO')
            ->value('id');
        if ($byService) {
            return (int) $byService;
        }

        $byProduct = Product::query()
            ->whereKey($id)
            ->where('item_type', 'SERVICIO')
            ->value('id');

        return $byProduct ? (int) $byProduct : null;
    }
}
