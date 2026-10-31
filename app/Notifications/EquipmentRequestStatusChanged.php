<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EquipmentRequestStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $reference,
        public readonly string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'equipment_request_status',
            'reference' => $this->reference,
            'status' => $this->status,
            'message' => 'Your equipment request '.$this->reference.' is now '.$this->status.'.',
        ];
    }
}
