<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssistanceStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $applicationId,
        public readonly string $status,
        public readonly ?string $reason = null,
        public readonly ?string $reference = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{type: string, application_id: int, status: string, message: string, reason: ?string, reference: ?string} */
    public function toArray(object $notifiable): array
    {
        $type = $this->status === 'completed'
            ? 'assistance_distribution_completed'
            : 'assistance_application_status';
        $message = match ($this->status) {
            'approved' => 'Your assistance application was approved by the MAO.',
            'rejected' => 'The MAO rejected your assistance application.',
            'returned' => 'The MAO returned your assistance application for correction.',
            'completed' => 'Your approved assistance supplies have been distributed.',
            default => 'Your assistance application status was updated.',
        };

        return [
            'type' => $type,
            'application_id' => $this->applicationId,
            'status' => $this->status,
            'message' => $message,
            'reason' => $this->reason,
            'reference' => $this->reference,
        ];
    }
}
