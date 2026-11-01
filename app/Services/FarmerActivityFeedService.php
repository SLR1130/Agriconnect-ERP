<?php

namespace App\Services;

use App\Models\Farmer;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FarmerActivityFeedService
{
    /**
     * Build the complete owner-scoped service feed in SQL. Each row is either
     * backed by a persisted event or clearly identified as a record snapshot.
     */
    public function query(Farmer $farmer): Builder
    {
        $latestApplicationHistory = DB::table('assistance_status_histories')
            ->selectRaw('application_id, MAX(id) AS latest_id')
            ->groupBy('application_id');

        $applications = DB::table('assistance_applications')
            ->join('assistance_programs', 'assistance_programs.id', '=', 'assistance_applications.program_id')
            ->leftJoinSub($latestApplicationHistory, 'latest_application_history', fn ($join) => $join->on('latest_application_history.application_id', '=', 'assistance_applications.id'))
            ->leftJoin('assistance_status_histories as application_history', 'application_history.id', '=', 'latest_application_history.latest_id')
            ->where('assistance_applications.farmer_id', $farmer->id)
            ->selectRaw("'Assistance application' AS activity_type")
            ->selectRaw("assistance_applications.id AS source_id, 'record' AS source_kind, assistance_programs.name AS title_primary, NULL AS title_secondary, assistance_applications.status AS activity_status, COALESCE(application_history.created_at, assistance_applications.updated_at) AS activity_at, assistance_applications.id AS related_id")
            ->selectRaw("CASE WHEN application_history.id IS NULL THEN 'application_snapshot' WHEN application_history.from_status IS NULL THEN 'application_submitted' WHEN application_history.to_status = 'submitted' THEN 'application_resubmitted' ELSE 'application_status_changed' END AS event_key, application_history.to_status AS event_status, COALESCE(application_history.notes, assistance_applications.review_notes) AS event_notes, NULL AS event_quantity");

        $latestEquipmentTransaction = DB::table('equipment_transactions')
            ->whereNotNull('equipment_request_id')
            ->selectRaw('equipment_request_id, MAX(id) AS latest_id')
            ->groupBy('equipment_request_id');

        $equipmentRequests = DB::table('equipment_requests')
            ->join('equipment_assets', 'equipment_assets.id', '=', 'equipment_requests.equipment_asset_id')
            ->leftJoinSub($latestEquipmentTransaction, 'latest_equipment_transaction', fn ($join) => $join->on('latest_equipment_transaction.equipment_request_id', '=', 'equipment_requests.id'))
            ->leftJoin('equipment_transactions as request_transaction', 'request_transaction.id', '=', 'latest_equipment_transaction.latest_id')
            ->where('equipment_requests.farmer_id', $farmer->id)
            ->selectRaw("'Equipment request' AS activity_type")
            ->selectRaw("equipment_requests.id AS source_id, 'record' AS source_kind, equipment_assets.name AS title_primary, NULL AS title_secondary, equipment_requests.status AS activity_status, COALESCE(request_transaction.created_at, equipment_requests.updated_at) AS activity_at, equipment_requests.id AS related_id")
            ->selectRaw("CASE WHEN request_transaction.id IS NULL THEN 'equipment_snapshot' ELSE request_transaction.action END AS event_key, equipment_requests.status AS event_status, COALESCE(request_transaction.notes, equipment_requests.review_notes) AS event_notes, NULL AS event_quantity");

        $registration = DB::table('farmer_registration_histories')
            ->join('farmers', 'farmers.id', '=', 'farmer_registration_histories.farmer_id')
            ->where('farmer_registration_histories.farmer_id', $farmer->id)
            ->selectRaw("'Registration update' AS activity_type")
            ->selectRaw("farmer_registration_histories.id AS source_id, 'history' AS source_kind, 'Farmer registration' AS title_primary, NULL AS title_secondary, farmers.registration_status AS activity_status, farmer_registration_histories.created_at AS activity_at, farmers.id AS related_id")
            ->selectRaw("'registration_update' AS event_key, farmer_registration_histories.to_status AS event_status, farmer_registration_histories.reason AS event_notes, NULL AS event_quantity");

        $farms = DB::table('farms')
            ->where('farmer_id', $farmer->id)
            ->selectRaw("'Farm record' AS activity_type")
            ->selectRaw("id AS source_id, 'snapshot' AS source_kind, name AS title_primary, NULL AS title_secondary, COALESCE(status, 'registered') AS activity_status, created_at AS activity_at, id AS related_id")
            ->selectRaw("'farm_recorded' AS event_key, NULL AS event_status, NULL AS event_notes, NULL AS event_quantity");

        $distributions = DB::table('assistance_distributions')
            ->join('assistance_applications', 'assistance_applications.id', '=', 'assistance_distributions.application_id')
            ->join('assistance_programs', 'assistance_programs.id', '=', 'assistance_applications.program_id')
            ->join('supply_batches', 'supply_batches.id', '=', 'assistance_distributions.supply_batch_id')
            ->join('supply_items', 'supply_items.id', '=', 'supply_batches.supply_item_id')
            ->where('assistance_applications.farmer_id', $farmer->id)
            ->selectRaw("'Assistance received' AS activity_type")
            ->selectRaw("assistance_distributions.id AS source_id, 'transaction' AS source_kind, assistance_programs.name AS title_primary, supply_items.name AS title_secondary, assistance_distributions.status AS activity_status, COALESCE(assistance_distributions.distributed_at, assistance_distributions.created_at) AS activity_at, assistance_distributions.application_id AS related_id")
            ->selectRaw("'assistance_distributed' AS event_key, assistance_distributions.status AS event_status, assistance_distributions.reference AS event_notes, assistance_distributions.quantity AS event_quantity");

        $cropRecords = DB::table('crop_production_records')
            ->where('farmer_id', $farmer->id)
            ->selectRaw("'Crop record' AS activity_type")
            ->selectRaw("id AS source_id, 'snapshot' AS source_kind, crop_type AS title_primary, NULL AS title_secondary, status AS activity_status, created_at AS activity_at, id AS related_id")
            ->selectRaw("'crop_recorded' AS event_key, NULL AS event_status, NULL AS event_notes, NULL AS event_quantity");

        $livestockRecords = DB::table('livestock_records')
            ->where('farmer_id', $farmer->id)
            ->selectRaw("'Livestock record' AS activity_type")
            ->selectRaw("id AS source_id, 'snapshot' AS source_kind, animal_type AS title_primary, NULL AS title_secondary, status AS activity_status, created_at AS activity_at, id AS related_id")
            ->selectRaw("'livestock_recorded' AS event_key, NULL AS event_status, NULL AS event_notes, NULL AS event_quantity");

        $fisheryRecords = DB::table('fishery_records')
            ->where('farmer_id', $farmer->id)
            ->selectRaw("'Fisheries record' AS activity_type")
            ->selectRaw("id AS source_id, 'snapshot' AS source_kind, fishery_type AS title_primary, NULL AS title_secondary, 'recorded' AS activity_status, created_at AS activity_at, id AS related_id")
            ->selectRaw("'fishery_recorded' AS event_key, NULL AS event_status, NULL AS event_notes, NULL AS event_quantity");

        $incidents = DB::table('pest_disease_incidents')
            ->where('farmer_id', $farmer->id)
            ->selectRaw("'Pest or disease report' AS activity_type")
            ->selectRaw("id AS source_id, 'snapshot' AS source_kind, incident_type AS title_primary, NULL AS title_secondary, status AS activity_status, created_at AS activity_at, id AS related_id")
            ->selectRaw("'incident_recorded' AS event_key, NULL AS event_status, NULL AS event_notes, NULL AS event_quantity");

        $sources = [
            $applications,
            $equipmentRequests,
            $registration,
            $farms,
            $distributions,
            $cropRecords,
            $livestockRecords,
            $fisheryRecords,
            $incidents,
        ];

        if (DB::getSchemaBuilder()->hasTable('production_submissions')) {
            $latestProductionHistory = DB::table('production_submission_histories')
                ->selectRaw('production_submission_id, MAX(id) AS latest_id')
                ->groupBy('production_submission_id');

            $sources[] = DB::table('production_submissions')
                ->join('farms as submission_farms', 'submission_farms.id', '=', 'production_submissions.farm_id')
                ->leftJoinSub($latestProductionHistory, 'latest_production_history', fn ($join) => $join->on('latest_production_history.production_submission_id', '=', 'production_submissions.id'))
                ->leftJoin('production_submission_histories as submission_history', 'submission_history.id', '=', 'latest_production_history.latest_id')
                ->where('production_submissions.farmer_id', $farmer->id)
                ->selectRaw("'Agricultural submission' AS activity_type")
                ->selectRaw("production_submissions.id AS source_id, 'record' AS source_kind, production_submissions.category AS title_primary, submission_farms.name AS title_secondary, production_submissions.status AS activity_status, COALESCE(submission_history.created_at, production_submissions.updated_at) AS activity_at, production_submissions.id AS related_id")
                ->selectRaw("CASE WHEN submission_history.id IS NULL THEN 'production_snapshot' WHEN submission_history.from_status IS NULL THEN 'production_submitted' WHEN submission_history.to_status = 'pending' THEN 'production_resubmitted' ELSE 'production_status_changed' END AS event_key, submission_history.to_status AS event_status, COALESCE(submission_history.notes, production_submissions.review_notes) AS event_notes, NULL AS event_quantity");
        }

        $union = array_shift($sources);
        foreach ($sources as $source) {
            $union->unionAll($source);
        }

        return DB::query()->fromSub($union, 'farmer_activity')
            ->select('farmer_activity.*')
            ->orderByDesc('activity_at')
            ->orderBy('activity_type')
            ->orderBy('source_kind')
            ->orderByDesc('source_id');
    }

    /** @param Collection<int, object> $rows */
    public function present(Collection $rows): Collection
    {
        $productionSubjects = collect();
        $submissionIds = $rows->where('activity_type', 'Agricultural submission')->pluck('related_id')->unique()->values();
        if ($submissionIds->isNotEmpty()) {
            $productionSubjects = DB::table('production_submissions')
                ->whereIn('id', $submissionIds)
                ->get(['id', 'payload'])
                ->mapWithKeys(function (object $submission): array {
                    $payload = is_array($submission->payload) ? $submission->payload : json_decode((string) $submission->payload, true);
                    $subject = data_get($payload, 'crop_type') ?: data_get($payload, 'animal_type') ?: data_get($payload, 'fishery_type');

                    return [(int) $submission->id => $subject];
                });
        }

        return $rows->map(function (object $row) use ($productionSubjects): array {
            $status = (string) $row->activity_status;
            $eventStatus = (string) ($row->event_status ?? $status);
            $event = $this->eventCopy($row->activity_type, $row->event_key, $eventStatus);
            $title = $row->title_primary;
            $detail = $row->title_secondary;
            $url = match ($row->activity_type) {
                'Assistance application' => route('farmer.applications.show', $row->related_id),
                'Equipment request' => route('farmer.equipment-requests.show', $row->related_id),
                'Registration update' => route('farmer.farms'),
                'Farm record' => route('farmer.farms').'#farm-'.$row->related_id,
                'Assistance received' => route('farmer.applications.show', $row->related_id),
                'Crop record' => route('farmer.production', ['section' => 'crops']),
                'Livestock record' => route('farmer.production', ['section' => 'livestock']),
                'Fisheries record' => route('farmer.production', ['section' => 'fisheries']),
                'Pest or disease report' => route('farmer.production', ['section' => 'incidents']),
                'Agricultural submission' => route('farmer.production.submissions.show', $row->related_id),
                default => route('farmer.activities'),
            };

            if ($row->activity_type === 'Agricultural submission') {
                $category = str((string) $title)->headline()->toString();
                $subject = $productionSubjects[(int) $row->related_id] ?? null;
                $title = $subject ? $category.' · '.$subject : $category.' submission';
            }

            if ($row->activity_type === 'Assistance received') {
                $event['description'] = 'Assistance received: '.number_format((float) $row->event_quantity, 2).' '.$detail.'.';
                $detail = $row->event_notes ? 'Reference '.(string) $row->event_notes : null;
            }

            $nextStep = $this->nextStep($row->activity_type, $status);

            return [
                'record_id' => (int) $row->related_id,
                'title' => $title,
                'detail' => $detail,
                'type' => $row->activity_type,
                'status' => $status,
                'event_status' => $eventStatus,
                'event_description' => $event['description'],
                'event_notes' => $row->activity_type === 'Assistance received' ? null : $row->event_notes,
                'date_label' => $event['date_label'],
                'date' => $row->activity_at ? Carbon::parse($row->activity_at) : null,
                'next_step' => $nextStep,
                'url' => $url,
            ];
        });
    }

    private function eventCopy(string $type, string $key, string $status): array
    {
        $statusLabel = str($status)->replace('_', ' ')->headline()->toString();

        if ($type === 'Equipment request' && $key !== 'equipment_snapshot') {
            $actionLabel = str($key)->replace('_', ' ')->headline()->toString();
            $description = match ($key) {
                'requested' => 'Equipment request submitted.',
                'approved' => 'MAO approved the equipment request.',
                'rejected' => 'MAO declined the equipment request.',
                'issued' => 'Equipment issued to the farmer.',
                'returned' => 'Equipment return recorded.',
                'cancelled' => 'Equipment request cancelled.',
                default => 'Equipment transaction recorded: '.$actionLabel.'.',
            };

            return ['description' => $description, 'date_label' => $actionLabel];
        }

        return match ($key) {
            'application_submitted' => ['description' => 'Application submitted for MAO review.', 'date_label' => 'Submitted'],
            'application_resubmitted' => ['description' => 'Corrected application resubmitted for review.', 'date_label' => 'Resubmitted'],
            'application_status_changed' => ['description' => 'Application status recorded as '.$statusLabel.'.', 'date_label' => 'Status recorded'],
            'application_snapshot' => ['description' => 'Current application record; no status-history entry is available.', 'date_label' => 'Record last updated'],
            'equipment_snapshot' => ['description' => 'Current equipment request record; no transaction history is available.', 'date_label' => 'Record last updated'],
            'production_submitted' => ['description' => 'Production information submitted for MAO review.', 'date_label' => 'Submitted'],
            'production_resubmitted' => ['description' => 'Corrected production information resubmitted.', 'date_label' => 'Resubmitted'],
            'production_status_changed' => ['description' => 'Production submission status recorded as '.$statusLabel.'.', 'date_label' => 'Status recorded'],
            'production_snapshot' => ['description' => 'Current production submission record; no status-history entry is available.', 'date_label' => 'Record last updated'],
            'registration_update' => ['description' => 'Farmer registration status recorded as '.$statusLabel.'.', 'date_label' => 'Registration update'],
            'assistance_distributed' => ['description' => 'Assistance distribution recorded.', 'date_label' => 'Distributed'],
            'farm_recorded' => ['description' => 'Farm record added.', 'date_label' => 'Recorded'],
            'crop_recorded' => ['description' => 'Crop record snapshot.', 'date_label' => 'Recorded'],
            'livestock_recorded' => ['description' => 'Livestock record snapshot.', 'date_label' => 'Recorded'],
            'fishery_recorded' => ['description' => 'Fisheries record snapshot.', 'date_label' => 'Recorded'],
            'incident_recorded' => ['description' => 'Pest or disease report snapshot.', 'date_label' => 'Reported'],
            default => $type.' record snapshot.',
        };
    }

    private function nextStep(string $type, string $status): ?string
    {
        return match ([$type, $status]) {
            ['Assistance application', 'submitted'] => 'Waiting for MAO review.',
            ['Assistance application', 'returned'] => 'Read the MAO feedback and correct your application.',
            ['Assistance application', 'approved'] => 'Check this application for distribution updates.',
            ['Assistance application', 'rejected'] => 'Read the MAO decision and feedback.',
            ['Equipment request', 'pending'] => 'The requested schedule awaits MAO review.',
            ['Equipment request', 'approved'] => 'Check the approved schedule and wait for the issue update.',
            ['Equipment request', 'issued'] => 'Follow the approved schedule and coordinate the equipment return.',
            ['Equipment request', 'rejected'] => 'Read the MAO decision and feedback.',
            ['Agricultural submission', 'pending'] => 'Waiting for MAO review.',
            ['Agricultural submission', 'returned'] => 'The MAO requested a correction. Read the feedback and correct your submission.',
            ['Agricultural submission', 'rejected'] => 'Read the MAO decision and feedback.',
            ['Registration update', 'pending'] => 'Your registration is awaiting MAO review.',
            ['Registration update', 'returned'] => 'Review the MAO feedback on My Farms.',
            ['Registration update', 'rejected'] => 'Review the MAO decision on My Farms.',
            default => null,
        };
    }
}
