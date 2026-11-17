<?php

namespace App\Services;

use App\Models\AssistanceProgram;
use App\Models\AssistanceProgramDocumentRequirement;
use App\Models\AssistanceProgramRequirementVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramRequirementVersionService
{
    /**
     * Save an editable draft. Published versions and versions already referenced
     * by applications are never edited in place.
     */
    public function saveDraft(AssistanceProgram $program, array $data, int $actorId): AssistanceProgramRequirementVersion
    {
        return DB::transaction(function () use ($program, $data, $actorId): AssistanceProgramRequirementVersion {
            $lockedProgram = AssistanceProgram::query()->lockForUpdate()->findOrFail($program->id);
            $draft = $lockedProgram->requirementVersions()->where('status', 'draft')->orderByDesc('version_number')->first();
            if (! $draft) {
                $draft = new AssistanceProgramRequirementVersion([
                    'program_id' => $lockedProgram->id,
                    'version_number' => ((int) $lockedProgram->requirementVersions()->max('version_number')) + 1,
                    'status' => 'draft',
                    'source' => 'staff',
                    'created_by' => $actorId,
                ]);
            }

            $draft->fill([
                'eligibility_rules' => $data['eligibility_rules'] ?? [],
                'opens_on' => $data['opens_on'] ?? null,
                'closes_on' => $data['closes_on'] ?? null,
                'created_by' => $draft->created_by ?: $actorId,
            ]);
            $draft->save();
            $draft->documentRequirements()->get()->each->delete();

            foreach (array_values($data['requirements'] ?? []) as $index => $item) {
                AssistanceProgramDocumentRequirement::create([
                    'requirement_version_id' => $draft->id,
                    'requirement_key' => $item['key'],
                    'display_name' => $item['display_name'],
                    'description' => $item['description'] ?? null,
                    'instructions' => $item['instructions'] ?? null,
                    'is_required' => ($item['required'] ?? 'required') === 'required',
                    'accepted_mime_types' => array_values($item['accepted_mime_types']),
                    'max_size_bytes' => (int) round(((float) $item['max_size_mib']) * 1024 * 1024),
                    'display_order' => $index,
                ]);
            }

            return $draft->load('documentRequirements');
        });
    }

    public function publish(AssistanceProgramRequirementVersion $version, int $actorId): AssistanceProgramRequirementVersion
    {
        return DB::transaction(function () use ($version, $actorId): AssistanceProgramRequirementVersion {
            $lockedVersion = AssistanceProgramRequirementVersion::query()
                ->with('documentRequirements')
                ->lockForUpdate()
                ->findOrFail($version->id);
            if ($lockedVersion->status !== 'draft') {
                throw ValidationException::withMessages(['requirements' => 'Only an unpublished draft can be published.']);
            }

            $program = AssistanceProgram::query()->lockForUpdate()->findOrFail($lockedVersion->program_id);
            $lockedVersion->update([
                'status' => 'published',
                'published_by' => $actorId,
                'published_at' => now()->utc(),
            ]);

            // Keep the existing program fields synchronized for established
            // catalog queries, while this version remains the audit snapshot.
            $program->update([
                'eligibility_rules' => $lockedVersion->eligibility_rules ?? [],
                'opens_on' => $lockedVersion->opens_on?->toDateString(),
                'closes_on' => $lockedVersion->closes_on?->toDateString(),
            ]);

            return $lockedVersion->fresh('documentRequirements', 'publisher');
        });
    }

    /**
     * Compatibility for programs created outside the new authoring workflow.
     * This is labelled as a legacy snapshot, never a claimed historical
     * publication event, and has no invented document requirements.
     */
    public function ensureLegacySnapshot(AssistanceProgram $program): AssistanceProgramRequirementVersion
    {
        $current = $program->currentRequirementVersion()->first();
        if ($current) {
            return $current;
        }

        return DB::transaction(function () use ($program): AssistanceProgramRequirementVersion {
            $lockedProgram = AssistanceProgram::query()->lockForUpdate()->findOrFail($program->id);
            $current = $lockedProgram->currentRequirementVersion()->first();
            if ($current) {
                return $current;
            }

            return $lockedProgram->requirementVersions()->create([
                'version_number' => ((int) $lockedProgram->requirementVersions()->max('version_number')) + 1,
                'status' => 'legacy',
                'source' => 'legacy_snapshot',
                'eligibility_rules' => $lockedProgram->eligibility_rules ?? [],
                'opens_on' => $lockedProgram->opens_on?->toDateString(),
                'closes_on' => $lockedProgram->closes_on?->toDateString(),
                'created_by' => null,
                'published_by' => null,
                'published_at' => null,
            ]);
        });
    }
}
