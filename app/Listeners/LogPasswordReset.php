<?php

// app/Listeners/LogPasswordReset.php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;

class LogPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        Log::info('Password reset completed', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'time' => now()
        ]);
    }
}
