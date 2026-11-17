<?php

namespace App\Services;

use App\Models\AgriculturalAnnouncement;
use App\Models\AgriculturalAnnouncementRevision;
use App\Models\AssistanceProgram;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class AgriculturalAnnouncementPresenter
{
    public function __construct(private readonly AgriculturalSchemaCapabilities $schema) {}

    /** @return array{content:array,bodyHtml:HtmlString,revision:?AgriculturalAnnouncementRevision,media:\Illuminate\Support\Collection,program:?AssistanceProgram} */
    public function present(AgriculturalAnnouncement $announcement, bool $public = false, ?AgriculturalAnnouncementRevision $revision = null): array
    {
        if ($this->schema->announcementRevisionsReady()) {
            $revision ??= $public
                ? $announcement->publishedRevision
                : ($announcement->currentRevision ?? $announcement->publishedRevision);
        } else {
            $revision = null;
        }
        $content = $revision?->content ?? ($public ? $announcement->publicContent() : $announcement->editorContent());
        $media = $revision?->media ?? collect();
        $program = null;

        if (! empty($content['assistance_program_id'])) {
            $program = AssistanceProgram::query()->find($content['assistance_program_id']);
            if ($public && $program?->status !== 'open') {
                $program = null;
            }
        }

        return [
            'content' => $content,
            'bodyHtml' => new HtmlString(Str::markdown((string) ($content['body'] ?? ''), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ])),
            'revision' => $revision,
            'media' => $media,
            'program' => $program,
        ];
    }
}
