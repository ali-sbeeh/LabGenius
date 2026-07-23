<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
class Notification extends Model
{
    use HasFactory ;
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'is_read',
        'type'
    ];

    protected static function booted()
    {
        static::created(function ($notification) {
            try {
                \Illuminate\Support\Facades\Http::timeout(1)->post('http://localhost:8085/broadcast', [
                    'id' => $notification->id,
                    'user_id' => $notification->user_id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'is_read' => $notification->is_read,
                    'created_at' => $notification->created_at ? $notification->created_at->toIso8601String() : now()->toIso8601String(),
                ]);
            } catch (\Exception $e) {
                // Ignore if websocket server is not running
            }
        });
    }

    public function user() { return $this->belongsTo(User::class); }
}
