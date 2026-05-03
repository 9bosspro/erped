<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('รหัสผ่านของคุณถูกเปลี่ยนแปลง / Password Changed')
            ->line('รหัสผ่านบัญชีของคุณถูกเปลี่ยนแปลงเรียบร้อยแล้ว')
            ->line('Your account password has been changed.')
            ->line('หากคุณไม่ได้ดำเนินการนี้ กรุณาติดต่อทีมสนับสนุนทันที')
            ->line('If you did not make this change, please contact support immediately.')
            ->action('เข้าสู่ระบบ / Login', url('/'));
    }
}
