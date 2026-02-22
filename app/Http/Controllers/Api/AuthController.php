<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Inicializar sistema - Crear primer super admin
     */
    public function initialize(Request $request)
    {
        // Verificar si ya hay usuarios en el sistema
        if (User::count() > 0) {
            return response()->json([
                'message' => 'Sistema ya inicializado',
                'status' => 'error'
            ], 400);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        try {
            // Ejecutar seeder completo de roles y permisos automáticamente
            $this->runRolesAndPermissionsSeeder();

            // Obtener rol de super admin
            $superAdminRole = Role::where('name', 'super_admin')->first();

            // Crear primer usuario super admin
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $superAdminRole->id,
                'user_type' => 'system',
                'active' => true,
                'email_verified_at' => now(),
            ]);

            // Crear token de acceso
            $token = $user->createToken('API_INIT_TOKEN', ['*'])->plainTextToken;

            return response()->json([
                'message' => 'Sistema inicializado exitosamente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->display_name
                ],
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al inicializar sistema: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Login - Autenticación
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas',
                'status' => 'error'
            ], 401);
        }

        if (!$user->active) {
            return response()->json([
                'message' => 'Usuario inactivo',
                'status' => 'error'
            ], 401);
        }

        if ($user->isLocked()) {
            return response()->json([
                'message' => 'Usuario bloqueado',
                'status' => 'error'
            ], 401);
        }

        // Registrar login exitoso
        $user->recordSuccessfulLogin($request->ip());

        // Crear token
        $abilities = $user->role ? $user->role->getAllPermissions() : ['*'];
        $token = $user->createToken('API_ACCESS_TOKEN', $abilities)->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? $user->role->display_name : 'Sin rol',
                'company_id' => $user->company_id,
                'permissions' => $abilities
            ],
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout exitoso'
        ]);
    }

    /**
     * Información del usuario autenticado
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('role', 'company');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? $user->role->display_name : 'Sin rol',
                'company' => $user->company ? $user->company->razon_social : null,
                'permissions' => $user->getAllPermissions(),
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at
            ]
        ]);
    }

    /**
     * Crear usuarios adicionales (solo super admin)
     */
    public function createUser(Request $request)
    {
        if (!$request->user()->hasRole('super_admin')) {
            return response()->json([
                'message' => 'No tienes permisos para crear usuarios',
                'status' => 'error'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', Password::min(8)],
            'role_name' => 'required|string|exists:roles,name',
            'company_id' => 'nullable|integer|exists:companies,id',
            'user_type' => 'required|in:system,user,api_client',
        ]);

        try {
            $role = Role::where('name', $request->role_name)->first();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $role->id,
                'company_id' => $request->company_id,
                'user_type' => $request->user_type,
                'active' => true,
                'email_verified_at' => now(),
            ]);

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->display_name,
                    'user_type' => $user->user_type
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear usuario: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Ejecutar seeder completo de roles y permisos automáticamente
     */
    private function runRolesAndPermissionsSeeder()
    {
        // Instanciar el seeder y ejecutarlo directamente sin setCommand
        $seeder = new RolesAndPermissionsSeeder();
        
        // Ejecutar solo la creación de permisos y roles, no usuarios por defecto
        $seeder->runPermissionsAndRolesOnly();
    }

    /**
     * Obtener información del sistema
     */
    public function systemInfo()
    {
        $userCount = User::count();
        $isInitialized = $userCount > 0;

        return response()->json([
            'system_initialized' => $isInitialized,
            'user_count' => $userCount,
            'roles_count' => Role::count(),
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'database_connected' => $this->checkDatabaseConnection(),
        ]);
    }

    /**
     * Verificar conexión a la base de datos
     */
    private function checkDatabaseConnection(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Solicitar recuperación de contraseña (envía token por email o lo devuelve en desarrollo)
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user || !$user->active) {
            return response()->json([
                'message' => 'No existe un usuario activo con ese correo.',
                'status' => 'error',
            ], 404);
        }

        $token = Str::random(64);
        $expiresAt = Carbon::now()->addMinutes(config('auth.passwords.users.expire', 60));

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Opcional: enviar email con el enlace (requiere configurar mail)
        $resetUrl = (config('app.frontend_url') ?: config('app.url')) . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($request->email);
        $expireMinutes = config('auth.passwords.users.expire', 60);
        $body = "Use este enlace para restablecer su contraseña (válido {$expireMinutes} minutos): {$resetUrl}. Si no solicitó esto, ignore este correo.";
        try {
            \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Recuperación de contraseña - ' . config('app.name'));
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo enviar email de recuperación: ' . $e->getMessage());
            // En desarrollo se puede devolver el token en la respuesta (no en producción)
            if (config('app.debug')) {
                return response()->json([
                    'message' => 'Token generado. En producción configure mail. Token (solo debug): ' . $token,
                    'status' => 'ok',
                    'token' => $token,
                    'email' => $request->email,
                ]);
            }
        }

        return response()->json([
            'message' => 'Si el correo existe, recibirá instrucciones para restablecer su contraseña.',
            'status' => 'ok',
        ]);
    }

    /**
     * Restablecer contraseña con token recibido por email
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if (!$record) {
            return response()->json([
                'message' => 'Token inválido o expirado.',
                'status' => 'error',
            ], 400);
        }

        $expireMinutes = config('auth.passwords.users.expire', 60);
        if (Carbon::parse($record->created_at)->addMinutes($expireMinutes)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'El token ha expirado. Solicite uno nuevo.',
                'status' => 'error',
            ], 400);
        }

        if (!Hash::check($request->token, $record->token)) {
            return response()->json([
                'message' => 'Token inválido o expirado.',
                'status' => 'error',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['password' => Hash::make($request->password)]);
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente. Ya puede iniciar sesión.',
            'status' => 'ok',
        ]);
    }

    /**
     * Solicitud de acceso al sistema (registro). Envía correo al administrador y confirmación al usuario.
     */
    public function requestAccess(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'nullable|string|max:500',
        ]);

        $name = $request->name;
        $email = $request->email;
        $message = $request->message ?? '';

        $appName = config('app.name');

        // Enviar correo al administrador (primer super_admin o correo por defecto)
        $admin = User::whereHas('role', function ($q) {
            $q->where('name', 'super_admin');
        })->where('active', true)->first();

        $adminEmail = $admin?->email ?? config('mail.from.address');

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Nueva solicitud de acceso a {$appName}\n\n" .
                "Nombre: {$name}\n" .
                "Email: {$email}\n" .
                ($message ? "Mensaje: {$message}\n" : '') .
                "\nPor favor revise y active la cuenta del usuario si corresponde.",
                function ($msg) use ($adminEmail, $appName) {
                    $msg->to($adminEmail)
                        ->subject("Solicitud de acceso - {$appName}");
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo enviar email de solicitud al admin: ' . $e->getMessage());
        }

        // Confirmación al usuario que solicitó acceso
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Hola {$name},\n\n" .
                "Hemos recibido tu solicitud de acceso a {$appName}. " .
                "Un administrador revisará tu solicitud y te contactará cuando tu cuenta esté disponible.\n\n" .
                "Saludos,\nEquipo {$appName}",
                function ($msg) use ($email, $appName) {
                    $msg->to($email)
                        ->subject("Solicitud recibida - {$appName}");
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo enviar email de confirmación al usuario: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Solicitud enviada correctamente. Recibirás un correo de confirmación y un administrador te contactará.',
            'status' => 'ok',
        ]);
    }
}