<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Boleta;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Pet;
use App\Models\Product;
use App\Models\Route;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExportReportService
{
    private const MAX_ROWS = 5000;

    public function companyId(Request $request): int
    {
        return (int) (
            $request->attributes->get('scope_company_id')
            ?? $request->integer('company_id')
            ?? $request->user()?->company_id
            ?? 0
        );
    }

    public function dataset(Request $request, string $dataset): array
    {
        $companyId = $this->companyId($request);
        if ($companyId <= 0) {
            return [];
        }

        $from = $request->get('date_from');
        $to = $request->get('date_to');

        return match ($dataset) {
            'clients' => $this->exportClients($companyId),
            'pets' => $this->exportPets($companyId),
            'appointments' => $this->exportAppointments($companyId, $from, $to),
            'invoices' => $this->exportInvoices($companyId, $from, $to),
            'products' => $this->exportProducts($companyId, 'PRODUCTO'),
            'services' => $this->exportProducts($companyId, 'SERVICIO'),
            'staff' => $this->exportStaff($companyId),
            'vehicles' => $this->exportVehicles($companyId),
            'routes' => $this->exportRoutes($companyId, $from, $to),
            default => [],
        };
    }

    public function report(Request $request, string $reportId): array
    {
        $companyId = $this->companyId($request);
        if ($companyId <= 0) {
            return [];
        }

        $from = $request->get('date_from', now()->startOfMonth()->toDateString());
        $to = $request->get('date_to', now()->toDateString());

        return match ($reportId) {
            'appointments-full' => $this->reportAppointmentsFull($companyId, $from, $to),
            'appointments-pending' => $this->reportAppointmentsPending($companyId),
            'appointments-cancelled' => $this->reportAppointmentsCancelled($companyId, $from, $to),
            'routes-daily' => $this->reportRoutesDaily($companyId, $from, $to),
            'clients-master' => $this->reportClientsMaster($companyId),
            'clients-active' => $this->reportClientsActive($companyId, 90),
            'clients-inactive' => $this->reportClientsInactive($companyId, 90),
            'pets-master' => $this->reportPetsMaster($companyId),
            'financial-invoices' => $this->reportFinancialInvoices($companyId, $from, $to),
            'financial-cash-flow' => $this->reportCashFlow($companyId, $from, $to),
            'financial-expenses' => $this->reportExpenses($companyId, $from, $to),
            'financial-pending-payments' => $this->reportPendingPayments($companyId),
            'inventory-stock' => $this->reportInventoryStock($companyId),
            'inventory-low-stock' => $this->reportInventoryLowStock($companyId),
            'staff-roster' => $this->reportStaffRoster($companyId),
            'audit-duplicates-clients' => $this->reportDuplicateClients($companyId),
            default => [],
        };
    }

    private function exportClients(int $companyId): array
    {
        return Client::where('company_id', $companyId)
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'razon_social' => $c->razon_social,
                'nombre_comercial' => $c->nombre_comercial,
                'numero_documento' => $c->numero_documento,
                'email' => $c->email,
                'telefono' => $c->telefono,
                'distrito' => $c->distrito,
                'activo' => $c->activo ? 'Sí' : 'No',
                'nivel_fidelizacion' => $c->nivel_fidelizacion,
                'fecha_registro' => $c->fecha_registro?->format('Y-m-d'),
            ])
            ->all();
    }

    private function exportPets(int $companyId): array
    {
        return Pet::whereHas('client', fn ($q) => $q->where('company_id', $companyId))
            ->with('client:id,razon_social,nombre_comercial')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Pet $p) => [
                'id' => $p->id,
                'nombre' => $p->name,
                'especie' => $p->species,
                'raza' => $p->breed,
                'cliente' => $p->client?->razon_social ?? $p->client?->nombre_comercial,
                'peso' => $p->weight,
                'activo' => $p->fallecido ? 'Fallecido' : 'Activo',
            ])
            ->all();
    }

    private function exportAppointments(int $companyId, ?string $from, ?string $to): array
    {
        $q = Appointment::where('company_id', $companyId)
            ->with(['client:id,razon_social,nombre_comercial', 'pet:id,name,breed', 'vehicle:id,name,placa']);

        if ($from && $to) {
            $q->whereBetween('date', [$from, $to]);
        }

        return $q->orderByDesc('date')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Appointment $a) => $this->appointmentRow($a))
            ->all();
    }

    private function exportInvoices(int $companyId, ?string $from, ?string $to): array
    {
        $q = Invoice::where('company_id', $companyId)->with('client:id,razon_social,numero_documento');
        if ($from && $to) {
            $q->whereBetween('fecha_emision', [$from, $to]);
        }

        return $q->orderByDesc('fecha_emision')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Invoice $inv) => [
                'numero' => $inv->numero_completo,
                'fecha' => $inv->fecha_emision,
                'cliente' => $inv->client?->razon_social,
                'documento' => $inv->client?->numero_documento,
                'subtotal' => $inv->sub_total,
                'igv' => $inv->mto_igv,
                'total' => $inv->mto_imp_venta,
                'estado_sunat' => $inv->estado_sunat,
            ])
            ->all();
    }

    private function exportProducts(int $companyId, string $itemType): array
    {
        return Product::where('company_id', $companyId)
            ->where('item_type', $itemType)
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Product $p) => [
                'sku' => $p->sku ?? $p->code,
                'nombre' => $p->name,
                'precio' => $p->unit_price,
                'costo' => $p->cost_price,
                'stock' => $p->stock,
                'activo' => $p->active ? 'Sí' : 'No',
            ])
            ->all();
    }

    private function exportStaff(int $companyId): array
    {
        return User::where('company_id', $companyId)
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'nombre' => $u->name,
                'email' => $u->email,
                'activo' => $u->active ?? true,
            ])
            ->all();
    }

    private function exportVehicles(int $companyId): array
    {
        return Vehicle::where('company_id', $companyId)
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'nombre' => $v->name,
                'placa' => $v->placa,
                'estado' => $v->status,
                'activo' => $v->activo ? 'Sí' : 'No',
            ])
            ->all();
    }

    private function exportRoutes(int $companyId, ?string $from, ?string $to): array
    {
        $q = Route::where('company_id', $companyId)
            ->with('vehicle:id,name,placa')
            ->withCount('stops');
        if ($from && $to) {
            $q->whereBetween('date', [$from, $to]);
        }

        return $q->orderByDesc('date')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Route $r) => [
                'id' => $r->id,
                'fecha' => $r->date?->format('Y-m-d'),
                'nombre' => $r->name,
                'vehiculo' => $r->vehicle?->name ?? $r->vehicle?->placa,
                'estado' => $r->status,
                'paradas' => $r->stops_count,
            ])
            ->all();
    }

    private function appointmentRow(Appointment $a): array
    {
        $time = $a->time;
        if ($time instanceof \DateTimeInterface) {
            $time = $time->format('H:i');
        } else {
            $time = substr((string) $time, 0, 5);
        }

        return [
            'Fecha' => $a->date?->format('Y-m-d') ?? (string) $a->date,
            'Hora' => $time,
            'Cliente' => $a->client?->razon_social ?? $a->client?->nombre_comercial ?? '',
            'Mascota' => $a->pet?->name ?? '',
            'Raza' => $a->pet?->breed ?? '',
            'Servicios' => $a->service_name ?? '',
            'Vehículo' => $a->vehicle?->name ?? $a->vehicle?->placa ?? '',
            'Estado' => $a->status,
            'Precio' => 'S/ ' . number_format((float) $a->total, 2),
            'Pago' => $a->payment_status,
            'Notas' => $a->notes ?? '',
        ];
    }

    private function reportAppointmentsFull(int $companyId, string $from, string $to): array
    {
        return $this->exportAppointments($companyId, $from, $to);
    }

    private function reportAppointmentsPending(int $companyId): array
    {
        $end = now()->addDays(30)->toDateString();

        return Appointment::where('company_id', $companyId)
            ->whereBetween('date', [now()->toDateString(), $end])
            ->whereNotIn('status', ['Cancelada', 'Completada'])
            ->with('client:id,razon_social,telefono,direccion')
            ->orderBy('date')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(function (Appointment $a) {
                $row = $this->appointmentRow($a);

                return [
                    'Fecha' => $row['Fecha'],
                    'Hora' => $row['Hora'],
                    'Cliente' => $row['Cliente'],
                    'Teléfono' => $a->client?->telefono ?? '',
                    'Dirección' => $a->address ?? $a->client?->direccion ?? '',
                    'Servicios' => $row['Servicios'],
                    'Vehículo Asignado' => $row['Vehículo'],
                    'Estado' => $row['Estado'],
                ];
            })
            ->all();
    }

    private function reportAppointmentsCancelled(int $companyId, string $from, string $to): array
    {
        return Appointment::where('company_id', $companyId)
            ->where('status', 'Cancelada')
            ->whereBetween('date', [$from, $to])
            ->with('client:id,razon_social')
            ->orderByDesc('date')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Appointment $a) => [
                'Fecha' => $a->date?->format('Y-m-d'),
                'Cliente' => $a->client?->razon_social ?? '',
                'Servicio' => $a->service_name,
                'Motivo Cancelación' => $a->cancellation_reason ?? '',
                'Cancelado' => $a->cancelled_at?->format('Y-m-d H:i') ?? '',
            ])
            ->all();
    }

    private function reportRoutesDaily(int $companyId, string $from, string $to): array
    {
        return $this->exportRoutes($companyId, $from, $to);
    }

    private function reportClientsMaster(int $companyId): array
    {
        return Client::where('company_id', $companyId)
            ->withCount('pets')
            ->with(['pets:id,client_id,name,breed'])
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Client $c) => [
                'ID' => $c->id,
                'Nombre Completo' => $c->razon_social ?? $c->nombre_comercial,
                'DNI/NIF' => $c->numero_documento,
                'Email' => $c->email,
                'Teléfono 1' => $c->telefono,
                'Dirección' => $c->direccion,
                'Distrito' => $c->distrito,
                'Mascotas' => $c->pets->map(fn ($p) => $p->name . ' (' . ($p->breed ?? '') . ')')->join(', '),
                'Fecha Registro' => $c->fecha_registro?->format('Y-m-d'),
                'Última Cita' => $c->fecha_ultima_visita?->format('Y-m-d'),
                'Nivel' => $c->nivel_fidelizacion,
            ])
            ->all();
    }

    private function reportClientsActive(int $companyId, int $days): array
    {
        $since = now()->subDays($days)->toDateString();

        return Client::where('company_id', $companyId)
            ->where(function ($q) use ($since) {
                $q->where('fecha_ultima_visita', '>=', $since)
                    ->orWhereHas('appointments', fn ($aq) => $aq->where('date', '>=', $since));
            })
            ->withCount('appointments')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Client $c) => [
                'Cliente' => $c->razon_social ?? $c->nombre_comercial,
                'Última Cita' => $c->fecha_ultima_visita?->format('Y-m-d'),
                'Total Citas' => $c->appointments_count,
                'Nivel' => $c->nivel_fidelizacion,
            ])
            ->all();
    }

    private function reportClientsInactive(int $companyId, int $days): array
    {
        $cutoff = now()->subDays($days);

        return Client::where('company_id', $companyId)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('fecha_ultima_visita')
                    ->orWhere('fecha_ultima_visita', '<', $cutoff);
            })
            ->withCount('appointments')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Client $c) => [
                'Cliente' => $c->razon_social ?? $c->nombre_comercial,
                'Última Cita' => $c->fecha_ultima_visita?->format('Y-m-d') ?? '—',
                'Días Inactivo' => $c->fecha_ultima_visita
                    ? $c->fecha_ultima_visita->diffInDays(now())
                    : '—',
                'Total Citas Históricas' => $c->appointments_count,
                'Email' => $c->email,
                'Teléfono' => $c->telefono,
            ])
            ->all();
    }

    private function reportPetsMaster(int $companyId): array
    {
        return Pet::whereHas('client', fn ($q) => $q->where('company_id', $companyId))
            ->with('client:id,razon_social')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Pet $p) => [
                'ID Mascota' => $p->id,
                'Nombre' => $p->name,
                'Propietario' => $p->client?->razon_social,
                'Especie' => $p->species,
                'Raza' => $p->breed,
                'Peso' => $p->weight,
                'Sexo' => $p->gender ?? '',
            ])
            ->all();
    }

    private function reportFinancialInvoices(int $companyId, string $from, string $to): array
    {
        $invoices = Invoice::where('company_id', $companyId)
            ->whereBetween('fecha_emision', [$from, $to])
            ->with('client:id,razon_social')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Invoice $inv) => [
                'Tipo' => 'Factura',
                'Nº' => $inv->numero_completo,
                'Fecha' => $inv->fecha_emision,
                'Cliente' => $inv->client?->razon_social,
                'Subtotal' => $inv->sub_total,
                'IGV' => $inv->mto_igv,
                'Total' => $inv->mto_imp_venta,
                'SUNAT' => $inv->estado_sunat,
            ]);

        $boletas = Boleta::where('company_id', $companyId)
            ->whereBetween('fecha_emision', [$from, $to])
            ->with('client:id,razon_social')
            ->limit(500)
            ->get()
            ->map(fn (Boleta $b) => [
                'Tipo' => 'Boleta',
                'Nº' => $b->numero_completo,
                'Fecha' => $b->fecha_emision,
                'Cliente' => $b->client?->razon_social,
                'Subtotal' => $b->sub_total,
                'IGV' => $b->mto_igv,
                'Total' => $b->mto_imp_venta,
                'SUNAT' => $b->estado_sunat,
            ]);

        return $invoices->concat($boletas)->values()->all();
    }

    private function reportCashFlow(int $companyId, string $from, string $to): array
    {
        return CashMovement::where('company_id', $companyId)
            ->whereBetween('movement_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('movement_date')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (CashMovement $m) => [
                'Fecha' => $m->movement_date?->format('Y-m-d H:i'),
                'Concepto' => $m->description,
                'Tipo' => $m->type,
                'Monto' => $m->amount,
                'Forma Pago' => $m->payment_method ?? '',
            ])
            ->all();
    }

    private function reportExpenses(int $companyId, string $from, string $to): array
    {
        return CashMovement::where('company_id', $companyId)
            ->where('type', 'EXPENSE')
            ->whereBetween('movement_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderByDesc('movement_date')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (CashMovement $m) => [
                'Fecha' => $m->movement_date?->format('Y-m-d'),
                'Descripción' => $m->description,
                'Monto' => $m->amount,
                'Forma Pago' => $m->payment_method ?? '',
            ])
            ->all();
    }

    private function reportPendingPayments(int $companyId): array
    {
        return Appointment::where('company_id', $companyId)
            ->whereIn('status', ['Completada', 'En Proceso'])
            ->where('payment_status', '!=', 'Pagado')
            ->with('client:id,razon_social,email,telefono')
            ->orderByDesc('date')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Appointment $a) => [
                'Cita' => $a->id,
                'Cliente' => $a->client?->razon_social,
                'Email' => $a->client?->email,
                'Teléfono' => $a->client?->telefono,
                'Monto' => $a->total,
                'Fecha' => $a->date?->format('Y-m-d'),
                'Servicio' => $a->service_name,
            ])
            ->all();
    }

    private function reportInventoryStock(int $companyId): array
    {
        return Product::where('company_id', $companyId)
            ->where('item_type', 'PRODUCTO')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(function (Product $p) {
                $stock = (float) ($p->stock ?? 0);
                $min = (float) ($p->min_stock ?? 0);
                $estado = $stock <= 0 ? 'CRÍTICO' : ($stock < $min ? 'BAJO' : 'OK');

                return [
                    'SKU' => $p->sku ?? $p->code,
                    'Producto' => $p->name,
                    'Stock Actual' => $stock,
                    'Stock Mínimo' => $min,
                    'Valor Unitario' => 'S/ ' . number_format((float) $p->unit_price, 2),
                    'Valor Total' => 'S/ ' . number_format($stock * (float) $p->unit_price, 2),
                    'Estado' => $estado,
                ];
            })
            ->all();
    }

    private function reportInventoryLowStock(int $companyId): array
    {
        return Product::where('company_id', $companyId)
            ->where('item_type', 'PRODUCTO')
            ->whereColumn('stock', '<', 'min_stock')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Product $p) => [
                'SKU' => $p->sku ?? $p->code,
                'Producto' => $p->name,
                'Stock Actual' => $p->stock,
                'Stock Mínimo' => $p->min_stock,
            ])
            ->all();
    }

    private function reportStaffRoster(int $companyId): array
    {
        return User::where('company_id', $companyId)
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (User $u) => [
                'ID' => $u->id,
                'Nombre' => $u->name,
                'Email' => $u->email,
                'Estado' => ($u->active ?? true) ? 'Activo' : 'Inactivo',
            ])
            ->all();
    }

    private function reportDuplicateClients(int $companyId): array
    {
        $dupes = Client::where('company_id', $companyId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select('email', DB::raw('COUNT(*) as cnt'))
            ->groupBy('email')
            ->having('cnt', '>', 1)
            ->limit(100)
            ->pluck('email');

        $rows = [];
        foreach ($dupes as $email) {
            $clients = Client::where('company_id', $companyId)->where('email', $email)->withCount('appointments')->get();
            if ($clients->count() < 2) {
                continue;
            }
            $c1 = $clients[0];
            $c2 = $clients[1];
            $rows[] = [
                'Cliente 1' => $c1->razon_social ?? $c1->nombre_comercial,
                'Cliente 2' => $c2->razon_social ?? $c2->nombre_comercial,
                'Campo Duplicado' => 'Email',
                'Valor' => $email,
                'Total Citas 1' => $c1->appointments_count,
                'Total Citas 2' => $c2->appointments_count,
            ];
        }

        return $rows;
    }
}
