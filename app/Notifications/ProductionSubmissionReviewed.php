<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductionSubmissionReviewed extends Notification
{
    use Queueable;

    public function __construct(public readonly int $submissionId, public readonly string $status, public readonly ?string $notes) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'production_submission_review', 'submission_id' => $this->submissionId, 'status' => $this->status,
            'message' => 'Your agricultural information was '.$this->status.' by the MAO.', 'reason' => $this->notes];
    }
}
