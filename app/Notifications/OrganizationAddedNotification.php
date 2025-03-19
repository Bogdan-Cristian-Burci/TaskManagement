<?php

namespace App\Notifications;

use App\Models\Organisation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Organisation $organisation;

    /**
     * Create a new notification instance.
     *
     * @param Organisation $organisation
     */
    public function __construct(Organisation $organisation)
    {
        $this->organisation = $organisation;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = config('app.url');

        return (new MailMessage)
            ->subject('You have been added to ' . $this->organisation->name)
            ->line('You have been added to ' . $this->organisation->name . ' on ' . config('app.name'))
            ->line('You can use your existing credentials to log in and access this organization.')
            ->action('Go to Dashboard', $url)
            ->line('If you were not expecting this invitation, please contact the organization administrator.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'organization_added',
            'organisation_id' => $this->organisation->id,
            'organisation_name' => $this->organisation->name
        ];
    }
}
