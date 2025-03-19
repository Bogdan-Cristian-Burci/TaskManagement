<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $token;
    protected int $organisationId;

    /**
     * Create a new notification instance.
     *
     * @param string $token
     * @param int $organisationId
     */
    public function __construct(string $token, int $organisationId)
    {
        $this->token = $token;
        $this->organisationId = $organisationId;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $organisation = \App\Models\Organisation::find($this->organisationId);
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Invitation to join ' . ($organisation ? $organisation->name : 'an organization'))
            ->line('You have been invited to join ' . ($organisation ? $organisation->name : 'an organization') . ' on ' . config('app.name'))
            ->line('Please set your password to get started.')
            ->action('Set Password', $resetUrl)
            ->line('If you did not expect to receive an invitation, no further action is required.');
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
            'type' => 'organization_invitation',
            'organisation_id' => $this->organisationId,
        ];
    }
}
