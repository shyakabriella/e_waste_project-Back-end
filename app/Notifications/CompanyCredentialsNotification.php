<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyCredentialsNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $temporaryPassword
    ) {
    }

    /**
     * Send notification by email.
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build email message.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your E-Waste Account Login Credentials')
            ->view('email.company-credentials', [
                'user' => $notifiable,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => config('app.url'),
            ]);
    }
}
