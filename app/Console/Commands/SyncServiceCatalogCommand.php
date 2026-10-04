<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Service;
use App\Services\ServiceCatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncServiceCatalogCommand extends Command
{
    protected $signature = 'services:sync-catalog
                            {--company= : Solo una empresa}
                            {--dry-run : Mostrar cambios sin persistir}';

    protected $description = 'Backfill: productos SERVICIO → tabla services + espejo product.service_id';

    public function handle(ServiceCatalogService $catalog): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = Product::query()
            ->where('item_type', 'SERVICIO')
            ->orderBy('company_id')
            ->orderBy('id');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $products = $query->get();
        $created = 0;
        $linked = 0;
        $mirrored = 0;

        foreach ($products as $product) {
            $meta = is_array($product->metadata) ? $product->metadata : [];
            $code = $product->code ?: ('SRV-' . $product->id);

            $service = Service::query()
                ->where('company_id', $product->company_id)
                ->where(function ($q) use ($product, $code) {
                    if ($product->service_id) {
                        $q->where('id', $product->service_id);
                    }
                    $q->orWhere('code', $code);
                })
                ->first();

            if (! $service) {
                $pricing = $meta['pricing'] ?? [
                    'medium' => [
                        'price' => (float) ($product->unit_price ?? $product->sale_price ?? 0),
                        'cost' => (float) ($product->cost_price ?? 0),
                        'duration' => (int) ($meta['duration'] ?? 45),
                    ],
                ];

                $payload = [
                    'company_id' => $product->company_id,
                    'name' => $product->name,
                    'code' => $code,
                    'description' => $product->description,
                    'category' => $meta['category'] ?? null,
                    'area' => $meta['area'] ?? null,
                    'active' => (bool) $product->active,
                    'pricing_by_size' => (bool) ($meta['pricingBySize'] ?? true),
                    'pricing' => $pricing,
                    'breed_exceptions' => $meta['breedExceptions'] ?? [],
                    'required_products' => $meta['required_products'] ?? [],
                ];

                if ($dryRun) {
                    $this->line("[dry-run] Crear service desde product #{$product->id} ({$code})");
                    $created++;
                    continue;
                }

                $service = DB::transaction(function () use ($payload, $catalog) {
                    $service = Service::create($payload);
                    $catalog->syncProductMirror($service);

                    return $service;
                });
                $created++;
                $this->info("Creado service #{$service->id} desde product #{$product->id}");
                continue;
            }

            if ($product->service_id != $service->id) {
                if ($dryRun) {
                    $this->line("[dry-run] Vincular product #{$product->id} → service #{$service->id}");
                } else {
                    $product->update(['service_id' => $service->id]);
                }
                $linked++;
            }

            if (! $dryRun) {
                $catalog->syncProductMirror($service);
                $mirrored++;
            }
        }

        // Servicios sin espejo
        $servicesQuery = Service::query()->orderBy('id');
        if ($companyId) {
            $servicesQuery->where('company_id', $companyId);
        }

        foreach ($servicesQuery->get() as $service) {
            $hasMirror = Product::query()
                ->where('company_id', $service->company_id)
                ->where(function ($q) use ($service) {
                    $q->where('service_id', $service->id)
                        ->orWhere(function ($q2) use ($service) {
                            $q2->where('item_type', 'SERVICIO')->where('code', $service->code);
                        });
                })
                ->exists();

            if (! $hasMirror) {
                if ($dryRun) {
                    $this->line("[dry-run] Crear mirror product para service #{$service->id}");
                } else {
                    $catalog->syncProductMirror($service);
                }
                $mirrored++;
            }
        }

        $this->table(
            ['metric', 'count'],
            [
                ['services_created', $created],
                ['products_linked', $linked],
                ['mirrors_synced', $mirrored],
                ['dry_run', $dryRun ? 'yes' : 'no'],
            ]
        );

        return self::SUCCESS;
    }
}
