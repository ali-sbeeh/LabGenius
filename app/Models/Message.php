<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * After saving a message, broadcast it via the WebSocket server.
     */
    protected static function booted(): void
    {
        static::created(function (Message $message) {
            $message->load('sender', 'conversation');
            $conv   = $message->conversation;
            // The recipient is whoever is NOT the sender
            $recipientId = ($message->sender_id === $conv->customer_id)
                ? $conv->seller_id
                : $conv->customer_id;

            $payload = [
                'event'           => 'new_message',
                'conversation_id' => $message->conversation_id,
                'message_id'      => $message->id,
                'sender_id'       => $message->sender_id,
                'sender_name'     => $message->sender->full_name ?? 'Unknown',
                'sender_image'    => $message->sender->image ?? null,
                'body'            => $message->body,
                'is_read'         => $message->is_read,
                'created_at'      => $message->created_at->toIso8601String(),
                'recipient_id'    => $recipientId,
                // Forward as notification so AppContext shows toast
                'user_id'         => $recipientId,
                'title'           => 'New message from ' . ($message->sender->full_name ?? 'Unknown'),
                'message'         => mb_substr($message->body, 0, 80),
                'type'            => 'chat_message',
            ];

            try {
                \Illuminate\Support\Facades\Http::timeout(1)
                    ->post('http://localhost:8085/broadcast', $payload);
            } catch (\Exception) {
                // Ignore if WS server is not running
            }
        });
    }
}
