<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\ChatSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublicChatController extends Controller
{
    public function __construct(private ChatSettingsService $chatSettings)
    {
    }

    private function publicCompanyId(): int
    {
        return (int) config('smartpet.public_company_id', 1);
    }

    public function config(): JsonResponse
    {
        $companyId = $this->publicCompanyId();
        $settings = $this->chatSettings->get($companyId);

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => (bool) ($settings['enabled'] ?? true),
                'agent_name' => $settings['agent_name'] ?? 'Soporte SmartPet',
                'agent_role' => $settings['agent_role'] ?? 'Asistente en línea',
                'welcome_message' => $settings['welcome_message'] ?? '',
            ],
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        if (!$this->chatSettings->get($this->publicCompanyId())['enabled']) {
            return response()->json(['success' => false, 'message' => 'Chat no disponible'], 403);
        }

        $validator = Validator::make($request->all(), [
            'guest_token' => 'nullable|string|max:64',
            'visitor_name' => 'nullable|string|max:120',
            'visitor_email' => 'nullable|email|max:120',
            'message' => 'nullable|string|max:2000',
            'tracking_code' => 'nullable|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $companyId = $this->publicCompanyId();
        $token = $request->input('guest_token');

        $conversation = null;
        if ($token) {
            $conversation = ChatConversation::where('guest_token', $token)
                ->where('company_id', $companyId)
                ->where('status', 'open')
                ->first();
        }

        if (!$conversation) {
            $token = Str::random(48);
            $conversation = ChatConversation::create([
                'company_id' => $companyId,
                'guest_token' => $token,
                'visitor_name' => $request->input('visitor_name'),
                'visitor_email' => $request->input('visitor_email'),
                'tracking_code' => $request->input('tracking_code'),
                'status' => 'open',
            ]);

            $welcome = $this->chatSettings->get($companyId)['welcome_message'] ?? '';
            if ($welcome !== '') {
                $this->storeMessage($conversation, 'system', null, $welcome);
            }
        }

        if ($request->filled('message')) {
            $this->storeVisitorMessage($conversation, (string) $request->input('message'));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'guest_token' => $conversation->guest_token,
                'conversation_id' => $conversation->id,
                'messages' => $this->formatMessages($conversation),
            ],
        ]);
    }

    public function messages(Request $request, string $guestToken): JsonResponse
    {
        $conversation = $this->findConversation($guestToken);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversación no encontrada'], 404);
        }

        $afterId = (int) $request->input('after_id', 0);
        $query = $conversation->messages()->orderBy('id');
        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $query->get()->map(fn (ChatMessage $m) => $this->formatMessage($m)),
                'conversation_status' => $conversation->status,
            ],
        ]);
    }

    public function send(Request $request, string $guestToken): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $conversation = $this->findConversation($guestToken);
        if (!$conversation || $conversation->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'Conversación cerrada o no encontrada'], 404);
        }

        $body = (string) $request->input('body');
        $visitorMsg = $this->storeVisitorMessage($conversation, $body);

        $autoReply = $this->chatSettings->matchAutoReply($conversation->company_id, $body);
        $newMessages = [$this->formatMessage($visitorMsg)];

        $hasStaffReply = $conversation->messages()
            ->where('sender_type', 'staff')
            ->where('created_at', '>=', now()->subHours(2))
            ->exists();

        if ($autoReply && !$hasStaffReply) {
            $systemMsg = $this->storeMessage($conversation, 'system', null, $autoReply);
            $newMessages[] = $this->formatMessage($systemMsg);
        }

        return response()->json([
            'success' => true,
            'data' => ['messages' => $newMessages],
        ]);
    }

    private function findConversation(string $guestToken): ?ChatConversation
    {
        return ChatConversation::where('guest_token', $guestToken)
            ->where('company_id', $this->publicCompanyId())
            ->first();
    }

    private function storeVisitorMessage(ChatConversation $conversation, string $body): ChatMessage
    {
        return DB::transaction(function () use ($conversation, $body) {
            $msg = $this->storeMessage($conversation, 'visitor', null, $body);
            $conversation->increment('unread_staff_count');
            $conversation->update(['last_message_at' => now()]);

            return $msg;
        });
    }

    private function storeMessage(
        ChatConversation $conversation,
        string $senderType,
        ?int $userId,
        string $body
    ): ChatMessage {
        return ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => $senderType,
            'sender_user_id' => $userId,
            'body' => $body,
        ]);
    }

    private function formatMessages(ChatConversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('id')
            ->get()
            ->map(fn (ChatMessage $m) => $this->formatMessage($m))
            ->all();
    }

    private function formatMessage(ChatMessage $message): array
    {
        $sender = match ($message->sender_type) {
            'visitor' => 'user',
            'staff' => 'agent',
            default => 'agent',
        };

        return [
            'id' => (string) $message->id,
            'text' => $message->body,
            'sender' => $sender,
            'sender_type' => $message->sender_type,
            'timestamp' => $message->created_at?->toIso8601String(),
        ];
    }
}
