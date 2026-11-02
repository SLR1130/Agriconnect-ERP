<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Describes optional agricultural schemas introduced by additive migrations.
 * Requests can continue to serve legacy records before those migrations run,
 * while write paths that require the new schema fail closed with HTTP 503.
 */
class AgriculturalSchemaCapabilities
{
    private ?bool $announcementRevisions = null;
    private ?bool $announcementMetadata = null;
    private ?bool $requirementVersions = null;
    private ?bool $acknowledgments = null;
    private ?bool $documents = null;

    public function announcementRevisionsReady(): bool
    {
        return $this->announcementRevisions ??= Schema::hasTable('agricultural_announcement_revisions')
            && Schema::hasTable('agricultural_announcement_media')
            && Schema::hasColumn('agricultural_announcements', 'current_revision_id')
            && Schema::hasColumn('agricultural_announcements', 'published_revision_id');
    }

    public function announcementMetadataReady(): bool
    {
        return $this->announcementMetadata ??= Schema::hasTable('agricultural_announcements')
            && collect(['office', 'category', 'service_area', 'requirements_summary', 'contact_details', 'instructions'])
                ->every(fn (string $column): bool => Schema::hasColumn('agricultural_announcements', $column));
    }

    public function requirementVersionsReady(): bool
    {
        return $this->requirementVersions ??= Schema::hasTable('assistance_program_requirement_versions')
            && Schema::hasTable('assistance_program_document_requirements')
            && Schema::hasColumn('assistance_applications', 'requirement_version_id');
    }

    public function acknowledgmentsReady(): bool
    {
        return $this->acknowledgments ??= Schema::hasTable('assistance_application_acknowledgments')
            && collect([
                'application_reference', 'submission_key', 'submitted_at',
                'submitted_business_date', 'submission_channel',
            ])->every(fn (string $column): bool => Schema::hasColumn('assistance_applications', $column));
    }

    public function applicationSubmissionReady(): bool
    {
        return $this->requirementVersionsReady() && $this->acknowledgmentsReady();
    }

    public function documentWorkflowReady(): bool
    {
        return $this->documents ??= $this->applicationSubmissionReady()
            && Schema::hasTable('assistance_application_documents')
            && Schema::hasTable('assistance_application_document_events')
            && Schema::hasTable('assistance_application_document_access_logs')
            && Schema::hasTable('assistance_application_submission_snapshots');
    }
}
