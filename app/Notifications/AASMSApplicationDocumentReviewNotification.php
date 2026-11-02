<?php

namespace App\Notifications;

use App\Models\AssistanceApplicationDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AASMSApplicationDocumentReviewNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly AssistanceApplicationDocument $document) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $application = $this->document->application;
        $requirement = $this->document->requirement;
        $message = match ($this->document->review_status) {
            'verified' => 'The MAO verified your '.($requirement?->display_name ?? 'supporting').' document.',
            'correction_requested' => 'The MAO requested a correction to your '.($requirement?->display_name ?? 'supporting').' document. '.$this->document->farmer_correction_notes,
            default => 'The MAO did not accept your '.($requirement?->display_name ?? 'supporting').' document. '.$this->document->farmer_correction_notes,
        };

        return [
            'type' => 'assistance_document_review',
            'title' => 'Supporting document reviewed',
            'message' => trim($message),
            'application_id' => $application?->id,
            'application_reference' => $application?->application_reference,
            'status' => $this->document->review_status,
            'url' => $application ? route('farmer.applications.show', $application) : route('farmer.assistance'),
        ];
    }
}
