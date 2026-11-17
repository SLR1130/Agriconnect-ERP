<?php

namespace App\Notifications;

use App\Models\Farmer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FarmerRegistrationStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Farmer $farmer,
        private readonly string $status,
        private readonly ?string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'farmer_registration_status',
            'farmer_code' => $this->farmer->farmer_code,
            'status' => $this->status,
            'reason' => $this->reason,
            'message' => 'Your farmer registration is now '.str_replace('_', ' ', $this->status).'.',
        ];
    }
}
