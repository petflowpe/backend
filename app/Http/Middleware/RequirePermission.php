<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('permission:products.create|products.manage')
 * Basta con tener cualquiera de los permisos listados (separados por "|").
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$groups): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $required = collect($groups)
            ->flatMap(fn (string $g) => explode('|', $g))
            ->map(fn (string $p) => trim($p))
            ->filter()
            ->values()
            ->all();

        if ($required === [] || $user->hasAnyPermission($required)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'No tienes permiso para realizar esta acción.',
            'required_permissions' => $required,
        ], 403);
    }
}
