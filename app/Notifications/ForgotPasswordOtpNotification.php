<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForgotPasswordOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $otp
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your E-Waste Password Reset OTP')
            ->view('email.forgot-password-otp', [
                'user' => $notifiable,
                'otp' => $this->otp,
            ]);
    }
}
