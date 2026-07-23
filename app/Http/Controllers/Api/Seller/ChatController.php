<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Api\ApiBaseController;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends ApiBaseController
{
    /**
     * List all conversations for the seller.
     * GET /api/v1/seller/chat/conversations
     */
    public function conversations()
    {
        $userId = Auth::id();
        $conversations = Conversation::where('seller_id', $userId)
            ->with(['latestMessage', 'customer'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn($c) => $this->formatConversationSummary($c, $userId));

        return $this->successResponse($conversations, 'Conversations list');
    }

    /**
     * Get messages in a specific conversation.
     * GET /api/v1/seller/chat/conversations/{id}
     */
    public function show($id)
    {
        $userId = Auth::id();
        $conversation = Conversation::where('id', $id)
            ->where('seller_id', $userId)
            ->with(['messages.sender', 'customer', 'seller'])
            ->firstOrFail();

        // Mark incoming messages as read
        $unreadCount = $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();

        if ($unreadCount > 0) {
            $conversation->messages()
                ->where('sender_id', '!=', $userId)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            // Notify the original sender (customer) that their messages were read
            try {
                \Illuminate\Support\Facades\Http::timeout(1)->post('http://localhost:8085/broadcast', [
                    'event'           => 'messages_read',
                    'conversation_id' => $conversation->id,
                    'reader_id'       => $userId,
                    'sender_id'       => $conversation->customer_id, // the one who sent
                ]);
            } catch (\Exception) { /* ignore if WS server is not running */ }
        }

        return $this->successResponse($this->formatConversation($conversation, $userId), 'Conversation messages');
    }

    /**
     * Send a message in a conversation.
     * POST /api/v1/seller/chat/conversations/{id}/messages
     * Body: { body }
     */
    public function sendMessage(Request $request, $id)
    {
        $request->validate(['body' => 'required|string|max:2000']);

        $userId = Auth::id();
        $conversation = Conversation::where('id', $id)
            ->where('seller_id', $userId)
            ->firstOrFail();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $userId,
            'body'            => $request->body,
            'is_read'         => false,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $message->load('sender');

        return $this->successResponse($this->formatMessage($message), 'Message sent', 201);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formatConversation(Conversation $conv, int $userId): array
    {
        $other = ($userId === $conv->seller_id) ? $conv->customer : $conv->seller;

        return [
            'id'          => $conv->id,
            'partner'     => [
                'id'        => $other->id,
                'full_name' => $other->full_name,
                'image'     => $other->image,
            ],
            'messages'    => $conv->messages->map(fn($m) => $this->formatMessage($m))->values(),
            'unread_count'=> $conv->unreadCountFor($userId),
        ];
    }

    private function formatConversationSummary(Conversation $conv, int $userId): array
    {
        $other = ($userId === $conv->seller_id) ? $conv->customer : $conv->seller;

        return [
            'id'            => $conv->id,
            'partner'       => [
                'id'        => $other->id,
                'full_name' => $other->full_name,
                'image'     => $other->image,
            ],
            'last_message'  => $conv->latestMessage?->body,
            'last_message_at' => $conv->last_message_at?->toIso8601String(),
            'unread_count'  => $conv->unreadCountFor($userId),
        ];
    }

    private function formatMessage(Message $msg): array
    {
        return [
            'id'          => $msg->id,
            'sender_id'   => $msg->sender_id,
            'sender_name' => $msg->sender?->full_name ?? 'Unknown',
            'sender_image'=> $msg->sender?->image,
            'body'        => $msg->body,
            'is_read'     => $msg->is_read,
            'created_at'  => $msg->created_at?->toIso8601String(),
        ];
    }
}
