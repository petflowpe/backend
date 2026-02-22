<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Obtener configuración/preferencias del usuario autenticado
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $metadata = $user->metadata ?? [];

        $settings = [
            'theme' => $metadata['theme'] ?? 'system',
            'language' => $metadata['language'] ?? 'es',
            'notifications' => [
                'email' => $metadata['notifications']['email'] ?? true,
                'push' => $metadata['notifications']['push'] ?? true,
                'invoices' => $metadata['notifications']['invoices'] ?? true,
                'appointments' => $metadata['notifications']['appointments'] ?? true,
            ],
            'dashboard' => [
                'default_view' => $metadata['dashboard']['default_view'] ?? 'overview',
                'refresh_interval' => $metadata['dashboard']['refresh_interval'] ?? 300,
            ],
            'privacy' => [
                'show_phone' => $metadata['privacy']['show_phone'] ?? true,
                'show_email' => $metadata['privacy']['show_email'] ?? true,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Actualizar configuración del usuario
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'theme' => 'sometimes|in:light,dark,system',
            'language' => 'sometimes|string|max:10',
            'notifications' => 'sometimes|array',
            'notifications.email' => 'sometimes|boolean',
            'notifications.push' => 'sometimes|boolean',
            'notifications.invoices' => 'sometimes|boolean',
            'notifications.appointments' => 'sometimes|boolean',
            'dashboard' => 'sometimes|array',
            'dashboard.default_view' => 'sometimes|string|max:50',
            'dashboard.refresh_interval' => 'sometimes|integer|min:60|max:3600',
            'privacy' => 'sometimes|array',
            'privacy.show_phone' => 'sometimes|boolean',
            'privacy.show_email' => 'sometimes|boolean',
        ]);

        $metadata = $user->metadata ?? [];

        if (isset($validated['theme'])) {
            $metadata['theme'] = $validated['theme'];
        }
        if (isset($validated['language'])) {
            $metadata['language'] = $validated['language'];
        }
        if (isset($validated['notifications'])) {
            $metadata['notifications'] = array_merge($metadata['notifications'] ?? [], $validated['notifications']);
        }
        if (isset($validated['dashboard'])) {
            $metadata['dashboard'] = array_merge($metadata['dashboard'] ?? [], $validated['dashboard']);
        }
        if (isset($validated['privacy'])) {
            $metadata['privacy'] = array_merge($metadata['privacy'] ?? [], $validated['privacy']);
        }

        $user->metadata = $metadata;
        $user->save();

        $settings = [
            'theme' => $metadata['theme'] ?? 'system',
            'language' => $metadata['language'] ?? 'es',
            'notifications' => $metadata['notifications'] ?? [],
            'dashboard' => $metadata['dashboard'] ?? [],
            'privacy' => $metadata['privacy'] ?? [],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Configuración actualizada',
            'data' => $settings,
        ]);
    }
}
