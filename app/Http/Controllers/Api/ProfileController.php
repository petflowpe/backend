<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Obtener perfil del usuario autenticado
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('role', 'company');

        $metadata = $user->metadata ?? [];
        $avatarUrl = $metadata['avatar_url'] ?? null;
        if ($avatarUrl && !str_starts_with($avatarUrl, 'http')) {
            $avatarUrl = Storage::url($avatarUrl);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role' => $user->role?->name,
                'role_display' => $user->role?->display_name,
                'company_id' => $user->company_id,
                'company' => $user->company?->razon_social,
                'avatar_url' => $avatarUrl,
                'phone' => $metadata['phone'] ?? null,
                'position' => $metadata['position'] ?? null,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Actualizar perfil del usuario autenticado
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'current_password' => 'required_with:password',
            'password' => ['sometimes', 'nullable', Password::min(8)->letters()->mixedCase()->numbers()],
            'phone' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|max:2048', // 2MB
        ]);

        if (isset($validated['password']) && $validated['password']) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña actual no es correcta',
                ], 422);
            }
            $user->password = Hash::make($validated['password']);
            $user->password_changed_at = now();
            $user->force_password_change = false;
        }

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        $metadata = $user->metadata ?? [];
        if (array_key_exists('phone', $validated)) {
            $metadata['phone'] = $validated['phone'];
        }
        if (array_key_exists('position', $validated)) {
            $metadata['position'] = $validated['position'];
        }

        if ($request->hasFile('avatar')) {
            if (!empty($metadata['avatar_url'])) {
                Storage::disk('public')->delete($metadata['avatar_url']);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $metadata['avatar_url'] = $path;
        }

        $user->metadata = $metadata;
        $user->save();

        $avatarUrl = $metadata['avatar_url'] ?? null;
        if ($avatarUrl && !str_starts_with($avatarUrl, 'http')) {
            $avatarUrl = url(Storage::url($avatarUrl));
        }

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $avatarUrl,
                'phone' => $metadata['phone'] ?? null,
                'position' => $metadata['position'] ?? null,
            ],
        ]);
    }
}
