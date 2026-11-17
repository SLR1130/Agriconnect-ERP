<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assistance_program_requirement_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('assistance_programs')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('draft');
            $table->string('source', 30)->default('staff');
            $table->json('eligibility_rules')->nullable();
            $table->date('opens_on')->nullable();
            $table->date('closes_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['program_id', 'version_number'], 'aasms_req_versions_program_number_uq');
            $table->index(['program_id', 'status', 'version_number'], 'aasms_req_versions_current_idx');
        });

        Schema::create('assistance_program_document_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requirement_version_id')
                ->constrained('assistance_program_requirement_versions', 'id', 'aasms_doc_req_version_fk')
                ->restrictOnDelete();
            $table->string('requirement_key', 80);
            $table->string('display_name', 150);
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('accepted_mime_types');
            $table->unsignedInteger('max_size_bytes');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['requirement_version_id', 'requirement_key'], 'aasms_req_version_key_uq');
            $table->index(['requirement_version_id', 'display_order'], 'aasms_req_version_order_idx');
        });

        Schema::table('assistance_applications', function (Blueprint $table): void {
            $table->string('application_reference', 48)->nullable()->unique('aasms_app_reference_uq');
            $table->string('submission_key', 64)->nullable()->unique('aasms_app_submission_key_uq');
            $table->foreignId('requirement_version_id')
                ->nullable()
                ->constrained('assistance_program_requirement_versions')
                ->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->date('submitted_business_date')->nullable();
            $table->string('submission_channel', 32)->nullable();
            $table->index(['farmer_id', 'submitted_at'], 'aasms_app_farmer_submitted_idx');
        });

        Schema::create('assistance_application_acknowledgments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')
                ->unique('aasms_ack_application_uq')
                ->constrained('assistance_applications')
                ->restrictOnDelete();
            $table->string('application_reference', 48)->unique('aasms_ack_reference_uq');
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64);
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        // This is an explicitly labelled snapshot of the current legacy rules,
        // not evidence of when or by whom those rules were originally published.
        $now = now()->utc();
        DB::table('assistance_programs')->orderBy('id')->chunkById(100, function ($programs) use ($now): void {
            foreach ($programs as $program) {
                $rules = json_decode((string) ($program->eligibility_rules ?? ''), true);
                DB::table('assistance_program_requirement_versions')->insert([
                    'program_id' => $program->id,
                    'version_number' => 0,
                    'status' => 'legacy',
                    'source' => 'legacy_snapshot',
                    'eligibility_rules' => json_encode(is_array($rules) ? $rules : [], JSON_THROW_ON_ERROR),
                    'opens_on' => $program->opens_on,
                    'closes_on' => $program->closes_on,
                    'created_by' => null,
                    'published_by' => null,
                    'published_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_application_acknowledgments');

        Schema::table('assistance_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('requirement_version_id');
            $table->dropIndex('aasms_app_farmer_submitted_idx');
            $table->dropUnique('aasms_app_reference_uq');
            $table->dropUnique('aasms_app_submission_key_uq');
            $table->dropColumn([
                'application_reference', 'submission_key', 'submitted_at',
                'submitted_business_date', 'submission_channel',
            ]);
        });

        Schema::dropIfExists('assistance_program_document_requirements');
        Schema::dropIfExists('assistance_program_requirement_versions');
    }
};
