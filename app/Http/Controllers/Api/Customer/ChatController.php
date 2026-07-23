<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\ApiBaseController;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends ApiBaseController
{
    /**
     * List all sellers that the customer has had (or can start) a conversation with.
     * GET /api/v1/customer/chat/sellers
     * Returns all users with role=seller.
     */
    public function sellers()
    {
        $sellers = User::where('role', 'seller')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->select('id', 'full_name', 'image', 'location')
            ->get()
            ->map(function ($seller) {
                $customerId = Auth::id();
                $conv = Conversation::where('customer_id', $customerId)
                    ->where('seller_id', $seller->id)
                    ->first();

                return [
                    'id'            => $seller->id,
                    'full_name'     => $seller->full_name,
                    'image'         => $seller->image,
                    'location'      => $seller->location,
                    'conversation_id' => $conv?->id,
                    'unread_count'  => $conv ? $conv->unreadCountFor($customerId) : 0,
                    'last_message'  => $conv ? optional($conv->latestMessage)->body : null,
                    'last_message_at' => $conv?->last_message_at?->toIso8601String(),
                ];
            });

        return $this->successResponse($sellers, 'Sellers list');
    }

    /**
     * Get or create a conversation between the logged-in customer and a seller.
     * POST /api/v1/customer/chat/conversations
     * Body: { seller_id }
     */
    public function startConversation(Request $request)
    {
        $request->validate(['seller_id' => 'required|exists:users,id']);

        $seller = User::where('id', $request->seller_id)->where('role', 'seller')->firstOrFail();

        $conversation = Conversation::firstOrCreate([
            'customer_id' => Auth::id(),
            'seller_id'   => $seller->id,
        ]);

        $conversation->load(['messages.sender', 'seller', 'customer']);

        return $this->successResponse($this->formatConversation($conversation, Auth::id()), 'Conversation ready');
    }

    /**
     * List all conversations for the customer.
     * GET /api/v1/customer/chat/conversations
     */
    public function conversations()
    {
        $userId = Auth::id();
        $conversations = Conversation::where('customer_id', $userId)
            ->with(['latestMessage', 'seller'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn($c) => $this->formatConversationSummary($c, $userId));

        return $this->successResponse($conversations, 'Conversations list');
    }

    /**
     * Get messages in a specific conversation.
     * GET /api/v1/customer/chat/conversations/{id}
     */
    public function show($id)
    {
        $userId = Auth::id();
        $conversation = Conversation::where('id', $id)
            ->where('customer_id', $userId)
            ->with(['messages.sender', 'seller', 'customer'])
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

            // Notify the original sender (seller) that their messages were read
            try {
                \Illuminate\Support\Facades\Http::timeout(1)->post('http://localhost:8085/broadcast', [
                    'event'           => 'messages_read',
                    'conversation_id' => $conversation->id,
                    'reader_id'       => $userId,
                    'sender_id'       => $conversation->seller_id, // the one who sent
                ]);
            } catch (\Exception) { /* ignore if WS server is not running */ }
        }

        return $this->successResponse($this->formatConversation($conversation, $userId), 'Conversation messages');
    }

    /**
     * Send a message in a conversation.
     * POST /api/v1/customer/chat/conversations/{id}/messages
     * Body: { body }
     */
    public function sendMessage(Request $request, $id)
    {
        $request->validate(['body' => 'required|string|max:2000']);

        $userId = Auth::id();
        $conversation = Conversation::where('id', $id)
            ->where('customer_id', $userId)
            ->firstOrFail();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $userId,
            'body'            => $request->body,
            'is_read'         => false,
        ]);

        // Update last_message_at on conversation
        $conversation->update(['last_message_at' => now()]);

        $message->load('sender');

        return $this->successResponse($this->formatMessage($message), 'Message sent', 201);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formatConversation(Conversation $conv, int $userId): array
    {
        $other = ($userId === $conv->customer_id) ? $conv->seller : $conv->customer;

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
        $other = ($userId === $conv->customer_id) ? $conv->seller : $conv->customer;

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
            'id'         => $msg->id,
            'sender_id'  => $msg->sender_id,
            'sender_name'=> $msg->sender?->full_name ?? 'Unknown',
            'sender_image'=> $msg->sender?->image,
            'body'       => $msg->body,
            'is_read'    => $msg->is_read,
            'created_at' => $msg->created_at?->toIso8601String(),
        ];
    }
}
