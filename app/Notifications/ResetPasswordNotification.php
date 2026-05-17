<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('รีเซ็ตรหัสผ่าน')
            ->greeting('สวัสดี '.$notifiable->name.',')
            ->line('คุณได้รับอีเมลนี้เนื่องจากเราได้รับคำขอรีเซ็ตรหัสผ่านสำหรับบัญชีของคุณ')
            ->action('รีเซ็ตรหัสผ่าน', url(route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false)))
            ->line('ลิงค์นี้จะหมดอายุในอีก '.config('auth.passwords.users.expire', 60).' นาที')
            ->line('หากคุณไม่ได้ขอรีเซ็ตรหัสผ่าน คุณไม่ต้องทำอะไร')
            ->salutation('ด้วยความเคารพ');
    }
}
