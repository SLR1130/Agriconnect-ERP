<?php

namespace App\Services;

use App\Models\AssistanceApplication;
use App\Models\AssistanceProgram;
use App\Models\AssistanceProgramRequirementVersion;
use App\Models\AssistanceStatusHistory;
use App\Models\Farmer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssistanceApplicationSubmissionService
{
    public function __construct(
        private readonly AgriculturalBusinessCalendar $calendar,
        private readonly ProgramEligibilityService $eligibility,
        private readonly ProgramRequirementVersionService $versions,
        private readonly AssistanceApplicationDocumentService $documents,
        private readonly StaffAssistedIntakePolicy $staffAssistedPolicy,
    ) {}

    public function submit(AssistanceProgram $program, Farmer $farmer, string $purpose, string $submissionKey, int $actorId): AssistanceApplication
    {
        return $this->submitUsingActor($program, $farmer, $purpose, $submissionKey, User::findOrFail($actorId));
    }

    public function submitStaffAssisted(
        AssistanceProgram $program,
        Farmer $farmer,
        string $purpose,
        string $submissionKey,
        User $actor,
        string $identityConfirmationMethod,
        bool $syntheticQaAttested,
    ): AssistanceApplication {
        abort_unless($actor->canModuleAction('aasms', 'intake_assist'), 403);
        $this->staffAssistedPolicy->assertEnabledForIsolatedQa();

        if (! in_array($identityConfirmationMethod, $this->staffAssistedPolicy->identityConfirmationMethods(), true)) {
            throw ValidationException::withMessages(['identity_confirmation_method' => 'The configured isolated QA identity-attestation method is not available.']);
        }
        if (! $syntheticQaAttested) {
            throw ValidationException::withMessages(['synthetic_qa_attestation' => 'Confirm the clearly labelled synthetic QA attestation to continue.']);
        }

        $application = $this->submitUsingActor($program, $farmer, $purpose, $submissionKey, $actor, [
            'identity_confirmation_method' => $identityConfirmationMethod,
        ]);

        if ($application->submission_channel !== 'mao_staff_assisted') {
            throw ValidationException::withMessages(['program' => 'An application already exists through another submission channel. Open the existing AASMS application instead of creating a staff-assisted duplicate.']);
        }

        return $application;
    }

    /** @param array{identity_confirmation_method:string}|null $intake */
    private function submitUsingActor(
        AssistanceProgram $program,
        Farmer $farmer,
        string $purpose,
        string $submissionKey,
        User $actor,
        ?array $intake = null,
    ): AssistanceApplication {
        if ($existing = $this->existingApplication($program, $farmer, $submissionKey)) {
            return $existing;
        }

        $businessDate = $this->calendar->today();

        try {
            return DB::transaction(function () use ($program, $farmer, $purpose, $submissionKey, $actor, $businessDate, $intake): AssistanceApplication {
                $lockedFarmer = Farmer::query()->with(['farms', 'user'])->lockForUpdate()->findOrFail($farmer->id);
                $lockedProgram = AssistanceProgram::query()->lockForUpdate()->findOrFail($program->id);

                if ($existing = $this->existingApplication($lockedProgram, $lockedFarmer, $submissionKey)) {
                    return $existing;
                }

                $version = $this->versions->ensureLegacySnapshot($lockedProgram);
                $lockedProgram->setRelation('currentRequirementVersion', $version->load('documentRequirements'));
                $eligibility = $this->eligibility->requireEligible($lockedProgram, $lockedFarmer, $businessDate);
                $submittedAt = CarbonImmutable::now('UTC');
                $reference = $this->newReference($businessDate);
                $channel = $intake ? 'mao_staff_assisted' : 'farmer_portal';
                $intakeEvidence = $intake ? [
                    'actor_user_id' => $actor->id,
                    'actor_name' => $actor->name,
                    'identity_confirmation' => [
                        'method' => $intake['identity_confirmation_method'],
                        'recorded_at_utc' => $submittedAt->toIso8601String(),
                        'evidence_type' => 'synthetic_qa_staff_attestation',
                    ],
                    'consent_attestation' => [
                        'recorded_by_user_id' => $actor->id,
                        'recorded_at_utc' => $submittedAt->toIso8601String(),
                        'policy_version' => $this->staffAssistedPolicy->attestationPolicyVersion(),
                        'evidence_type' => 'synthetic_qa_only',
                        'statement' => 'No farmer signature or legal consent is asserted; this record exists only for isolated QA.',
                    ],
                ] : null;

                $application = AssistanceApplication::create([
                    'program_id' => $lockedProgram->id,
                    'farmer_id' => $lockedFarmer->id,
                    'purpose' => $purpose !== '' ? $purpose : null,
                    'eligibility_passed' => $eligibility['passed'],
                    'eligibility_notes' => $eligibility['notes'],
                    'status' => 'submitted',
                    'application_reference' => $reference,
                    'submission_key' => $submissionKey,
                    'requirement_version_id' => $version->id,
                    'submitted_at' => $submittedAt,
                    'submitted_business_date' => $businessDate->toDateString(),
                    'submission_channel' => $channel,
                ]);

                AssistanceStatusHistory::create([
                    'application_id' => $application->id,
                    'from_status' => null,
                    'to_status' => 'submitted',
                    'changed_by' => $actor->id,
                    'notes' => $intake
                        ? 'Application recorded through MAO staff-assisted intake; synthetic QA attestation only.'
                        : 'Farmer submitted application.',
                ]);

                $snapshot = $this->snapshot($application, $lockedFarmer, $lockedProgram, $version, $eligibility, $businessDate, $submittedAt, $intakeEvidence);
                $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $application->acknowledgment()->create([
                    'application_reference' => $reference,
                    'snapshot' => $snapshot,
                    'snapshot_sha256' => hash('sha256', $snapshotJson),
                    'generated_at' => $submittedAt,
                ]);
                $this->documents->recordInitialSubmissionSnapshot($application, $actor, $submittedAt);

                return $application->fresh(['program', 'requirementVersion', 'acknowledgment']);
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintFailure($exception)) {
                throw $exception;
            }

            $existing = $this->existingApplication($program, $farmer, $submissionKey);
            if ($existing) {
                return $existing;
            }

            // A colliding random reference or another unrelated unique key is
            // not treated as a successful duplicate submission.
            throw $exception;
        }
    }

    private function existingApplication(AssistanceProgram $program, Farmer $farmer, string $submissionKey): ?AssistanceApplication
    {
        $byProgram = AssistanceApplication::query()
            ->where('program_id', $program->id)
            ->where('farmer_id', $farmer->id)
            ->with('acknowledgment')
            ->first();

        if ($byProgram) {
            if (hash_equals((string) $byProgram->submission_key, $submissionKey)) {
                return $byProgram;
            }

            throw ValidationException::withMessages(['program' => 'Already applied. Open your existing application to view its reference and acknowledgment.']);
        }

        $byKey = AssistanceApplication::query()->where('submission_key', $submissionKey)->first();
        if ($byKey) {
            throw ValidationException::withMessages(['program' => 'This submission key is already associated with another application. Reload the program page before submitting.']);
        }

        return null;
    }

    private function snapshot(
        AssistanceApplication $application,
        Farmer $farmer,
        AssistanceProgram $program,
        AssistanceProgramRequirementVersion $version,
        array $eligibility,
        CarbonImmutable $businessDate,
        CarbonImmutable $submittedAt,
        ?array $intakeEvidence,
    ): array {
        $documents = $version->documentRequirements->map(fn ($requirement): array => [
            'key' => $requirement->requirement_key,
            'name' => $requirement->display_name,
            'description' => $requirement->description,
            'instructions' => $requirement->instructions,
            'required' => $requirement->is_required,
            'accepted_mime_types' => $requirement->accepted_mime_types,
            'max_size_bytes' => $requirement->max_size_bytes,
        ])->values()->all();

        return [
            'schema_version' => 1,
            'application' => [
                'id' => $application->id,
                'reference' => $application->application_reference,
                'status_at_submission' => 'submitted',
                'channel' => $application->submission_channel,
            ],
            'applicant' => [
                'farmer_id' => $farmer->id,
                'farmer_code' => $farmer->farmer_code,
                'name' => $farmer->user?->name,
                'barangay' => $farmer->barangay,
            ],
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'category' => $program->category,
            ],
            'office' => [
                'name' => config('agriculture.office_name'),
                'municipality' => config('agriculture.municipality'),
                'contact' => config('agriculture.office_contact'),
            ],
            'requirements_version' => [
                'id' => $version->id,
                'number' => $version->version_number,
                'source' => $version->source,
                'published_at' => $version->published_at?->utc()->toIso8601String(),
                'publisher_name' => $version->publisher?->name,
                'opens_on' => $version->opens_on?->toDateString(),
                'closes_on' => $version->closes_on?->toDateString(),
                'eligibility_rules' => $version->eligibility_rules ?? [],
                'documents' => $documents,
            ],
            'requirements_checklist' => [
                'state' => 'presented_at_submission',
                'items' => $documents,
            ],
            'submission' => [
                'submitted_at_utc' => $submittedAt->toIso8601String(),
                'business_date_manila' => $businessDate->toDateString(),
                'purpose' => $application->purpose,
            ],
            'eligibility' => [
                'passed' => $eligibility['passed'],
                'notes' => $eligibility['notes'],
                'rules' => $version->eligibility_rules ?? [],
            ],
            'document_receipt' => [
                'collection_status' => 'not_enabled_in_phase_6c_1',
                'received' => [],
                'missing' => null,
            ],
            ...($intakeEvidence ? ['intake' => $intakeEvidence] : []),
        ];
    }

    private function newReference(CarbonImmutable $businessDate): string
    {
        return 'AASMS-'.$businessDate->format('Y').'-'.strtoupper(bin2hex(random_bytes(8)));
    }

    private function isUniqueConstraintFailure(QueryException $exception): bool
    {
        $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return in_array($state, ['23000', '23505'], true)
            || $driverCode === 19
            || ($driverCode === 1062 && str_contains($message, 'duplicate'));
    }
}
