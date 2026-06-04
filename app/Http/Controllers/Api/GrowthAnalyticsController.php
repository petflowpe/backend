<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReview;
use App\Models\Pet;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GrowthAnalyticsController extends Controller
{
    private function resolveCompanyId(Request $request): ?int
    {
        return ScopeHelper::companyId($request)
            ?? ($request->user()?->hasRole('super_admin') && $request->filled('company_id')
                ? (int) $request->company_id
                : null);
    }

    public function overview(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        $clientsQuery = Client::where('company_id', $companyId)->where('activo', true);

        $loyaltyByLevel = (clone $clientsQuery)
            ->select('nivel_fidelizacion', DB::raw('COUNT(*) as count'), DB::raw('SUM(puntos_fidelizacion) as points'))
            ->groupBy('nivel_fidelizacion')
            ->get()
            ->map(fn ($row) => [
                'level' => $row->nivel_fidelizacion ?: 'Plata',
                'count' => (int) $row->count,
                'points' => (int) ($row->points ?? 0),
            ]);

        $atRiskDays = 60;
        $atRisk = (clone $clientsQuery)
            ->where(function ($q) use ($atRiskDays) {
                $q->whereNull('fecha_ultima_visita')
                    ->orWhere('fecha_ultima_visita', '<', now()->subDays($atRiskDays)->toDateString());
            })
            ->count();

        $reviewsQuery = ClientReview::where('company_id', $companyId);
        $reviewCount = (clone $reviewsQuery)->count();
        $avgRating = (clone $reviewsQuery)->avg('rating');

        $appointments30 = Appointment::where('company_id', $companyId)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->whereNotIn('status', ['Cancelada'])
            ->count();

        $completed30 = Appointment::where('company_id', $companyId)
            ->where('status', 'Completada')
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'loyalty_by_level' => $loyaltyByLevel,
                'clients_at_risk' => $atRisk,
                'total_active_clients' => (clone $clientsQuery)->count(),
                'reviews_count' => $reviewCount,
                'reviews_avg_rating' => round((float) $avgRating, 2),
                'appointments_last_30_days' => $appointments30,
                'revenue_completed_last_30_days' => (float) $completed30,
            ],
        ]);
    }

    public function appointmentTrends(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        $days = min(90, max(7, (int) $request->input('days', 60)));
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = Appointment::query()
            ->where('company_id', $companyId)
            ->where('date', '>=', $from->toDateString())
            ->whereNotIn('status', ['Cancelada'])
            ->selectRaw('date, COUNT(*) as appointments, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $byDate = $rows->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $row = $byDate->get($d);
            $series[] = [
                'date' => $d,
                'appointments' => (int) ($row->appointments ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
            ];
        }

        return response()->json(['success' => true, 'data' => $series]);
    }

    public function geographic(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        $clients = Client::query()
            ->where('company_id', $companyId)
            ->where('activo', true)
            ->withCount(['pets as active_pets_count' => fn ($q) => $q->where('fallecido', false)])
            ->withCount('appointments')
            ->withSum([
                'appointments as appointments_revenue' => fn ($q) => $q->where('status', 'Completada'),
            ], 'total')
            ->get();

        $items = $clients->map(function (Client $client) {
            $level = strtolower($client->nivel_fidelizacion ?? 'plata');
            $categoria = match ($level) {
                'oro', 'vip' => 'oro',
                'bronce' => 'bronce',
                default => 'plata',
            };

            return [
                'id' => (string) $client->id,
                'nombre' => $client->razon_social ?: $client->nombre_comercial,
                'categoria' => $categoria,
                'mascotas' => (int) $client->active_pets_count,
                'mascotasActivas' => (int) $client->active_pets_count,
                'distrito' => $client->distrito ?: 'Sin distrito',
                'direccion' => $client->direccion ?: '',
                'gastoMensual' => round((float) ($client->appointments_revenue ?? 0) / max(1, (int) $client->appointments_count), 2),
                'ultimaCita' => $client->fecha_ultima_visita?->format('Y-m-d'),
                'telefono' => $client->telefono,
                'puntos_fidelizacion' => (int) $client->puntos_fidelizacion,
                'citas' => (int) $client->appointments_count,
            ];
        });

        $byDistrict = $items->groupBy('distrito')->map(fn ($group, $distrito) => [
            'distrito' => $distrito,
            'clientes' => $group->count(),
            'citas' => $group->sum('citas'),
            'ingresos' => $group->sum('gastoMensual'),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'clients' => $items->values(),
                'by_district' => $byDistrict,
            ],
        ]);
    }

    public function segmentation(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        $clients = Client::where('company_id', $companyId)
            ->where('activo', true)
            ->withCount(['pets as active_pets_count' => fn ($q) => $q->where('fallecido', false)])
            ->get();

        $dist = ['oro' => 0, 'bronce' => 0, 'plata' => 0];
        $list = [];

        foreach ($clients as $client) {
            $active = (int) $client->active_pets_count;
            $categoria = $active >= 4 ? 'oro' : ($active >= 2 ? 'bronce' : 'plata');
            $dist[$categoria]++;
            $list[] = [
                'id' => (string) $client->id,
                'nombre' => $client->razon_social ?: $client->nombre_comercial,
                'mascotas' => $active,
                'mascotasActivas' => $active,
                'categoria' => $categoria,
                'ultimaCita' => $client->fecha_ultima_visita?->format('Y-m-d'),
                'email' => $client->email,
                'telefono' => $client->telefono,
            ];
        }

        $total = max(1, $clients->count());

        return response()->json([
            'success' => true,
            'data' => [
                'clients' => $list,
                'distribution' => [
                    'oro' => ['cantidad' => $dist['oro'], 'porcentaje' => round(100 * $dist['oro'] / $total, 1)],
                    'bronce' => ['cantidad' => $dist['bronce'], 'porcentaje' => round(100 * $dist['bronce'] / $total, 1)],
                    'plata' => ['cantidad' => $dist['plata'], 'porcentaje' => round(100 * $dist['plata'] / $total, 1)],
                ],
            ],
        ]);
    }

    public function mobilePatterns(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        $days = min(90, max(7, (int) $request->input('days', 30)));
        $from = now()->subDays($days - 1)->toDateString();

        $appointments = Appointment::query()
            ->where('company_id', $companyId)
            ->where('date', '>=', $from)
            ->whereNotIn('status', ['Cancelada'])
            ->whereNotNull('vehicle_id')
            ->get(['vehicle_id', 'district', 'status', 'total', 'duration', 'date']);

        $vehicles = Vehicle::where('company_id', $companyId)
            ->get(['id', 'name', 'placa', 'activo', 'status_override'])
            ->keyBy('id');

        $byVehicle = $appointments->groupBy('vehicle_id')->map(function ($group, $vehicleId) use ($vehicles) {
            $vehicle = $vehicles->get($vehicleId);
            $completed = $group->where('status', 'Completada');
            $districts = $group->groupBy(fn ($a) => $a->district ?: 'Sin distrito')
                ->map(fn ($dg, $district) => [
                    'district' => $district,
                    'appointments' => $dg->count(),
                    'completed' => $dg->where('status', 'Completada')->count(),
                    'revenue' => (float) $dg->where('status', 'Completada')->sum('total'),
                ])
                ->sortByDesc('appointments')
                ->values();

            $total = $group->count();
            $completedCount = $completed->count();

            return [
                'vehicle_id' => (int) $vehicleId,
                'vehicle_name' => $vehicle?->name ?? "Móvil #{$vehicleId}",
                'plate' => $vehicle?->placa,
                'vehicle_status' => $vehicle?->status_override ?: ($vehicle?->activo ? 'activo' : 'inactivo'),
                'total_appointments' => $total,
                'completed_appointments' => $completedCount,
                'completion_rate' => $total > 0 ? round(100 * $completedCount / $total, 1) : 0,
                'revenue' => (float) $completed->sum('total'),
                'avg_duration_minutes' => round((float) $group->avg('duration'), 0),
                'top_districts' => $districts->take(8),
            ];
        })->values();

        $heatmap = $appointments->groupBy(fn ($a) => ($a->district ?: 'Sin distrito') . '|' . $a->vehicle_id)
            ->map(fn ($g, $key) => [
                'district' => explode('|', $key)[0],
                'vehicle_id' => (int) explode('|', $key)[1],
                'count' => $g->count(),
            ])
            ->sortByDesc('count')
            ->take(50)
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'period_days' => $days,
                'vehicles' => $byVehicle,
                'district_vehicle_heatmap' => $heatmap,
                'summary' => [
                    'total_appointments' => $appointments->count(),
                    'active_vehicles' => $byVehicle->count(),
                    'unique_districts' => $appointments->pluck('district')->filter()->unique()->count(),
                ],
            ],
        ]);
    }
}
