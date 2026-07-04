<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPasswordGeneratedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $newPassword
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your New E-Waste Login Password')
            ->view('email.new-password-generated', [
                'user' => $notifiable,
                'newPassword' => $this->newPassword,
                'loginUrl' => config('app.url'),
            ]);
    }
}
