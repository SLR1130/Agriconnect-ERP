<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assistance_application_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('assistance_applications', 'id', 'aasms_doc_application_fk')->restrictOnDelete();
            $table->foreignId('requirement_version_id')->constrained('assistance_program_requirement_versions', 'id', 'aasms_doc_version_fk')->restrictOnDelete();
            $table->foreignId('requirement_id')->constrained('assistance_program_document_requirements', 'id', 'aasms_doc_requirement_fk')->restrictOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->foreignId('supersedes_document_id')->nullable();
            $table->foreign('supersedes_document_id', 'aasms_doc_supersedes_fk')
                ->references('id')->on('assistance_application_documents')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users', 'id', 'aasms_doc_uploader_fk')->restrictOnDelete();
            $table->string('original_filename', 180);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->char('sha256', 64);
            $table->string('storage_disk', 40)->default('application-documents');
            $table->string('storage_path', 500)->unique('aasms_doc_storage_path_uq');
            $table->string('scan_status', 20)->default('pending');
            $table->string('scan_engine', 80)->nullable();
            $table->timestamp('scan_completed_at')->nullable();
            $table->string('review_status', 30)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users', 'id', 'aasms_doc_reviewer_fk')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('farmer_correction_notes')->nullable();
            $table->text('internal_review_notes')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->unique(['application_id', 'requirement_id', 'version_number'], 'aasms_doc_version_number_uq');
            $table->index(['application_id', 'requirement_id', 'created_at'], 'aasms_doc_checklist_idx');
            $table->index(['application_id', 'review_status'], 'aasms_doc_review_status_idx');
            $table->index(['scan_status', 'created_at'], 'aasms_doc_scan_status_idx');
        });

        Schema::create('assistance_application_document_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('assistance_application_documents', 'id', 'aasms_doc_event_document_fk')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_scan_status', 20)->nullable();
            $table->string('to_scan_status', 20)->nullable();
            $table->string('from_review_status', 30)->nullable();
            $table->string('to_review_status', 30)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users', 'id', 'aasms_doc_event_actor_fk')->restrictOnDelete();
            $table->string('visibility', 20)->default('internal');
            $table->text('note')->nullable();
            $table->text('internal_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['document_id', 'created_at'], 'aasms_doc_event_timeline_idx');
        });

        Schema::create('assistance_application_document_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('assistance_application_documents', 'id', 'aasms_doc_access_document_fk')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users', 'id', 'aasms_doc_access_actor_fk')->restrictOnDelete();
            $table->string('access_action', 20);
            $table->timestamp('accessed_at');
            $table->timestamps();
            $table->index(['document_id', 'accessed_at'], 'aasms_doc_access_timeline_idx');
        });

        Schema::create('assistance_application_submission_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('assistance_applications', 'id', 'aasms_submission_app_fk')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('submission_type', 20);
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64);
            $table->foreignId('created_by')->constrained('users', 'id', 'aasms_submission_actor_fk')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['application_id', 'attempt_number'], 'aasms_submission_attempt_uq');
            $table->index(['application_id', 'submitted_at'], 'aasms_submission_timeline_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            'assistance_application_documents',
            'assistance_application_document_events',
            'assistance_application_document_access_logs',
            'assistance_application_submission_snapshots',
        ] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('AASMS document or submission evidence exists; this migration cannot be rolled back without a separately approved evidence-preservation plan.');
            }
        }

        Schema::dropIfExists('assistance_application_document_access_logs');
        Schema::dropIfExists('assistance_application_document_events');
        Schema::dropIfExists('assistance_application_documents');
        Schema::dropIfExists('assistance_application_submission_snapshots');
    }
};
