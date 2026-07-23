<?php

// app/Notifications/ResetPasswordNotification.php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{

    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * Create a new notification instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // بناء رابط إعادة التعيين
        // $resetUrl = url(config('app.frontend_url') . '/reset-password/' . $this->token . '?email=' . urlencode($notifiable->email));
        $resetUrl = config('app.frontend_url') . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->email);
        // أو يمكن استخدام رابط API
        $apiResetUrl = url('/api/v1/auth/reset-password') . '?token=' . $this->token . '&email=' . urlencode($notifiable->email);

        if (config('app.env') === 'local') {
            \Illuminate\Support\Facades\Log::info('Generated Reset Password URL', [
                'email' => $notifiable->email,
                'url' => $resetUrl
            ]);
        }

        // ✅ استخدم view بدل markdown (لأن markdown مش شغال)
        return (new MailMessage)
            ->subject('🔐 إعادة تعيين كلمة المرور - LapGeneus')
            ->view('emails.reset-password', [
                'user' => $notifiable,
                'resetUrl' => $resetUrl,
                'token' => $this->token,
                'email' => $notifiable->email,
                'expireMinutes' => config('auth.passwords.users.expire', 60)
            ]);

        /* return (new MailMessage)
            ->subject('🔐 إعادة تعيين كلمة المرور - LapGeneus')
            ->markdown('emails.reset-password', [
                'user' => $notifiable,
                'resetUrl' => $resetUrl,
                'apiResetUrl' => $apiResetUrl,
                'token' => $this->token,
                'email' => $notifiable->email,
                'expireMinutes' => config('auth.passwords.users.expire', 60)
            ]);
            */
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'إعادة تعيين كلمة المرور',
            'message' => 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني',
            'type' => 'password_reset'
        ];
    }
}
