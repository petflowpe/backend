<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get user notifications (solo las del usuario autenticado o de su empresa sin destinatario)
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $userId = $user?->id ?? 0;
        $companyId = $user?->company_id;

        $query = Notification::query();
        if ($userId) {
            $query->where(function ($q) use ($userId, $companyId) {
                $q->where('user_id', $userId);
                if ($companyId) {
                    $q->orWhere(function ($q2) use ($companyId) {
                        $q2->where('company_id', $companyId)->whereNull('user_id');
                    });
                }
            });
        } elseif ($companyId) {
            $query->where('company_id', $companyId)->whereNull('user_id');
        } else {
            $query->where('user_id', 0)->whereNull('company_id');
        }

        if ($request->has('unread_only')) {
            $query->where('read', false);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->limit($request->get('limit', 50))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead($id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída',
            'data' => $notification
        ]);
    }

    /**
     * Mark all notifications as read (solo las del usuario autenticado)
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        $userId = $user?->id ?? 0;
        $companyId = $user?->company_id;

        $query = Notification::where('read', false);
        if ($userId) {
            $query->where(function ($q) use ($userId, $companyId) {
                $q->where('user_id', $userId);
                if ($companyId) {
                    $q->orWhere(function ($q2) use ($companyId) {
                        $q2->where('company_id', $companyId)->whereNull('user_id');
                    });
                }
            });
        } elseif ($companyId) {
            $query->where('company_id', $companyId)->whereNull('user_id');
        }
        $query->update(['read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Todas las notificaciones marcadas como leídas'
        ]);
    }

    /**
     * Delete a notification (solo las propias del usuario)
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $notification = Notification::findOrFail($id);
        if ($notification->user_id && $notification->user_id !== ($user?->id)) {
            abort(403, 'No puede eliminar esta notificación');
        }
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notificación eliminada'
        ]);
    }

    /**
     * System utility to create notifications (internal or for other controllers)
     */
    public static function createNotification(array $data)
    {
        return Notification::create($data);
    }
}
