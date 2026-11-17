<?php

namespace App\Notifications;

use App\Models\AgriculturalAnnouncement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AgriculturalAnnouncementPublished extends Notification
{
    use Queueable;

    public function __construct(public readonly AgriculturalAnnouncement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'agricultural_announcement', 'announcement_id' => $this->announcement->id,
            'message' => 'MAO announcement: '.$this->announcement->title];
    }
}
