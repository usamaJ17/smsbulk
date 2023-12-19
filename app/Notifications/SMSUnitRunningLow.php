<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SMSUnitRunningLow extends Notification
{
    use Queueable;

    protected $remaining_unit;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($remaining_unit)
    {
        $this->remaining_unit = $remaining_unit;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     *
     * @return array
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     *
     * @return MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
                ->from(config('mail.from.address'), config('mail.from.name'))
                ->subject('SMS Unit Running Low Notice from '.config('app.name'))
                ->line('Your sms unit is running low. Your current sms unit '.$this->remaining_unit)
                ->action('Buy More', route('customer.subscriptions.index'))
                ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     *
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }
}
