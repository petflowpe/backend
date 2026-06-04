<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\ChatSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function __construct(private ChatSettingsService $chatSettings)
    {
    }

    private function resolveCompanyId(Request $request): ?int
    {
        return ScopeHelper::companyId($request)
            ?? ($request->user()?->hasRole('super_admin') && $request->filled('company_id')
                ? (int) $request->company_id
                : null);
    }

    public function settings(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->chatSettings->get($companyId),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'sometimes|boolean',
            'agent_name' => 'sometimes|string|max:120',
            'agent_role' => 'sometimes|string|max:120',
            'welcome_message' => 'sometimes|string|max:2000',
            'auto_replies' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $saved = $this->chatSettings->save($companyId, $request->only([
            'enabled',
            'agent_name',
            'agent_role',
            'welcome_message',
            'auto_replies',
        ]));

        return response()->json(['success' => true, 'data' => $saved]);
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'company_id requerido'], 422);
        }

        $status = $request->input('status', 'open');
        $conversations = ChatConversation::where('company_id', $companyId)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (ChatConversation $c) => [
                'id' => $c->id,
                'visitor_name' => $c->visitor_name ?: 'Visitante',
                'visitor_email' => $c->visitor_email,
                'tracking_code' => $c->tracking_code,
                'status' => $c->status,
                'unread_staff_count' => (int) $c->unread_staff_count,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $conversations]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = $this->findScoped($request, $id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        $messages = $conversation->messages()->orderBy('id')->get()->map(fn (ChatMessage $m) => [
            'id' => $m->id,
            'sender_type' => $m->sender_type,
            'body' => $m->body,
            'sender_user_id' => $m->sender_user_id,
            'created_at' => $m->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
                'messages' => $messages,
            ],
        ]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $conversation = $this->findScoped($request, $id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        $message = DB::transaction(function () use ($conversation, $request) {
            $msg = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'staff',
                'sender_user_id' => $request->user()?->id,
                'body' => (string) $request->input('body'),
            ]);

            $conversation->messages()
                ->where('sender_type', 'visitor')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $conversation->update([
                'last_message_at' => now(),
                'unread_staff_count' => 0,
                'assigned_user_id' => $conversation->assigned_user_id ?? $request->user()?->id,
            ]);

            return $msg;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $conversation = $this->findScoped($request, $id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        $conversation->messages()
            ->where('sender_type', 'visitor')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->update(['unread_staff_count' => 0]);

        return response()->json(['success' => true]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $conversation = $this->findScoped($request, $id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        $conversation->update(['status' => 'closed']);

        return response()->json(['success' => true]);
    }

    private function findScoped(Request $request, int $id): ?ChatConversation
    {
        $companyId = $this->resolveCompanyId($request);
        if (!$companyId) {
            return null;
        }

        return ChatConversation::where('company_id', $companyId)->where('id', $id)->first();
    }
}
