<?php

namespace Tests\Feature;

use App\Models\AssistanceApplication;
use App\Models\AssistanceApplicationAcknowledgment;
use App\Models\AssistanceClaimTicket;
use App\Models\AssistanceClaimTicketEvent;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceHandoverAcknowledgment;
use App\Models\AssistanceIdentityConfirmation;
use App\Models\AssistanceProgram;
use App\Models\Farmer;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Role;
use App\Models\SupplyBatch;
use App\Models\SupplyCategory;
use App\Models\SupplyItem;
use App\Models\UnitOfMeasurement;
use App\Models\User;
use App\Services\AssistanceHandoverService;
use App\Services\AssistanceService;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AASMSPhysicalHandoverTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agricultural_claim_tickets.enabled' => true,
            // The test suite uses disposable SQLite fixtures; operational mode
            // is exercised explicitly by the dedicated test below.
            'agricultural_claim_tickets.mode' => 'qa',
            'agricultural_claim_tickets.qa_mysql_database' => null,
            'agricultural_claim_handover.enabled' => true,
        ]);

        $this->data = $this->fixture([
            'modules.aasms.view', 'modules.aasms.distribute', 'modules.aisms.release',
            'modules.aasms.claim_ticket_view', 'modules.aasms.claim_ticket_issue',
            'modules.aasms.claim_ticket_verify', 'modules.aasms.claim_ticket_reissue',
            'modules.aasms.claim_ticket_history', 'modules.aasms.identity_confirm',
            'modules.aasms.handover_distribute',
        ]);
    }

    public function test_authorized_physical_handover_commits_one_inventory_distribution_ticket_application_history_ack_and_notification(): void
    {
        $this->data['application']->farmer->update(['barangay' => 'Labasan']);
        $originalAcknowledgment = $this->createApplicationAcknowledgment($this->data['application']);
        [$ticket, $confirmation] = $this->issueAndConfirm($this->data['application'], $this->data['staff']);
        $before = (string) $this->data['batch']->fresh()->quantity_available;

        $response = $this->postHandover($this->data['application'], $ticket, $confirmation, $this->data['batch'], 12);
        $ack = AssistanceHandoverAcknowledgment::sole();
        $response->assertRedirect(route('modules.aasms.handovers.acknowledgment', $ack));
        $this->assertSame('30.00', $before);
        $this->assertSame('18.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_movements', ['movement_type' => 'release', 'quantity' => '12.00', 'idempotency_key' => 'aasms-claim-ticket:'.$ticket->id]);
        $this->assertDatabaseCount('assistance_distributions', 1);
        $distribution = AssistanceDistribution::sole();
        $this->assertSame('Labasan', $distribution->farmer_barangay_at_distribution);
        $this->data['application']->farmer->update(['barangay' => 'Dayhagan']);
        $this->assertSame('Labasan', $distribution->fresh()->farmer_barangay_at_distribution);
        $this->assertSame($ticket->id, (int) $distribution->claim_ticket_id);
        $this->assertSame('redeemed', $ticket->fresh()->status);
        $this->assertSame($distribution->id, (int) $ticket->fresh()->redeemed_distribution_id);
        $this->assertSame('completed', $this->data['application']->fresh()->status);
        $this->assertDatabaseHas('assistance_status_histories', ['application_id' => $this->data['application']->id, 'from_status' => 'approved', 'to_status' => 'completed']);
        $this->assertDatabaseHas('assistance_claim_ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'redeemed', 'from_status' => 'issued', 'to_status' => 'redeemed']);
        $this->assertDatabaseHas('assistance_handover_events', ['distribution_id' => $distribution->id, 'event_type' => 'physical_handover_committed']);
        $this->assertSame('synthetic_qa_test_only', $ack->confirmation_method);
        $this->assertStringContainsString('SYNTHETIC QA ONLY', $ack->environment_label);
        $this->assertSame($originalAcknowledgment->id, $this->data['application']->fresh()->acknowledgment->id);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_operational_mode_requires_staff_attestation_and_records_an_in_person_handover(): void
    {
        config([
            'agricultural_claim_tickets.mode' => 'operational',
            'agricultural_claim_tickets.live_enabled' => true,
        ]);

        $ticket = $this->issueTicket($this->data['application'], $this->data['staff']);
        $token = Crypt::decryptString($ticket->token_ciphertext);
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => 'AGC1:'.$token])
            ->assertRedirect();
        $event = AssistanceClaimTicketEvent::query()
            ->where('ticket_id', $ticket->id)->where('outcome', 'valid')->latest('id')->firstOrFail();

        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.identity-confirm', $ticket), [
            'verification_event_id' => $event->id,
            'outcome' => 'confirmed',
        ])->assertSessionHasErrors('identity_attestation');

        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.identity-confirm', $ticket), [
            'verification_event_id' => $event->id,
            'outcome' => 'confirmed',
            'identity_attestation' => '1',
        ])->assertRedirect();

        $confirmation = AssistanceIdentityConfirmation::query()->where('verification_event_id', $event->id)->firstOrFail();
        $this->assertSame(AssistanceIdentityConfirmation::IN_PERSON_METHOD, $confirmation->method);
        $this->assertSame('in_person_match', data_get($confirmation->evidence_metadata, 'scenario'));

        $this->postHandover($this->data['application'], $ticket, $confirmation, $this->data['batch'], 12);
        $acknowledgment = AssistanceHandoverAcknowledgment::sole();
        $this->assertSame(AssistanceIdentityConfirmation::IN_PERSON_METHOD, $acknowledgment->confirmation_method);
        $this->assertSame('MAO-authorized physical assistance handover', $acknowledgment->environment_label);
        $this->assertSame('18.00', (string) $this->data['batch']->fresh()->quantity_available);
    }

    public function test_identical_retry_returns_existing_distribution_and_conflicting_retries_do_not_release_more_stock(): void
    {
        [$ticket, $confirmation] = $this->issueAndConfirm($this->data['application'], $this->data['staff']);
        $payload = $this->handoverPayload($confirmation, $this->data['batch'], 12);

        $this->actingAs($this->data['staff'])->post(route('modules.aasms.handovers.distribute', [$this->data['application'], $ticket]), $payload)->assertRedirect();
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.handovers.distribute', [$this->data['application'], $ticket]), $payload)->assertRedirect();
        $this->assertSame('18.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('assistance_distributions', 1);
        $this->assertDatabaseCount('assistance_handover_acknowledgments', 1);
        $this->assertDatabaseCount('notifications', 1);

        $payload['quantity'] = '11.00';
        $this->actingAs($this->data['staff'])
            ->post(route('modules.aasms.handovers.distribute', [$this->data['application'], $ticket]), $payload)
            ->assertSessionHasErrors('handover');
        $payload['quantity'] = '12.00';
        $payload['supply_batch_id'] = $this->secondBatch($this->data['item'], 30)->id;
        $this->actingAs($this->data['staff'])
            ->post(route('modules.aasms.handovers.distribute', [$this->data['application'], $ticket]), $payload)
            ->assertSessionHasErrors('handover');
        $conflicts = \App\Models\AssistanceHandoverEvent::query()
            ->where('claim_ticket_id', $ticket->id)
            ->where('event_type', 'handover_retry_conflict')
            ->get();
        $this->assertCount(2, $conflicts);
        $this->assertTrue($conflicts->contains(fn ($event) => data_get($event->safe_metadata, 'quantity_mismatch') === true));
        $this->assertTrue($conflicts->contains(fn ($event) => data_get($event->safe_metadata, 'batch_mismatch') === true));
        $this->assertSame('18.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_ticket_enrollment_blocks_the_legacy_aasms_distribution_route_but_unticketed_legacy_case_remains_compatible(): void
    {
        $ticket = $this->issueTicket($this->data['application'], $this->data['staff']);
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.distribute', $this->data['application']), [
            'supply_batch_id' => $this->data['batch']->id, 'quantity' => 12,
        ])->assertSessionHasErrors('application_id');
        $this->assertSame('30.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertSame('issued', $ticket->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);

        $legacyRole = Role::create(['name' => 'Legacy distribution QA', 'slug' => 'legacy_distribution_qa', 'permissions' => ['modules.aasms.distribute'], 'is_active' => true]);
        $legacyActor = User::factory()->create();
        $legacyActor->roles()->attach($legacyRole);
        $legacyApplication = $this->application('approved');
        $this->actingAs($legacyActor)->post(route('modules.aasms.distribute', $legacyApplication), [
            'supply_batch_id' => $this->data['batch']->id, 'quantity' => 12,
        ])->assertRedirect();
        $this->assertSame('18.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertSame('completed', $legacyApplication->fresh()->status);
        $this->assertDatabaseCount('assistance_distributions', 1);
    }

    public function test_direct_service_distribution_cannot_bypass_ticket_cohort_policy(): void
    {
        $this->issueTicket($this->data['application'], $this->data['staff']);
        try {
            app(AssistanceService::class)->distribute($this->data['application'], [
                'supply_batch_id' => $this->data['batch']->id, 'quantity' => 12,
            ], $this->data['staff']->id, app(InventoryService::class));
            $this->fail('Ticket-governed direct service distribution should be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('application_id', $exception->errors());
        }
        $this->assertSame('30.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_identity_confirmation_requires_successful_token_or_qr_event_and_exact_current_ticket(): void
    {
        $ticket = $this->issueTicket($this->data['application'], $this->data['staff']);
        $referenceResponse = $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => $ticket->ticket_reference]);
        $referenceEvent = AssistanceClaimTicketEvent::query()->where('ticket_id', $ticket->id)->where('verification_method', 'reference')->latest('id')->firstOrFail();
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.identity-confirm', $ticket), [
            'verification_event_id' => $referenceEvent->id, 'outcome' => 'confirmed',
        ])->assertSessionHasErrors('verification');

        $invalidResponse = $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => str_repeat('a', 64)]);
        $invalidEvent = AssistanceClaimTicketEvent::query()->where('outcome', 'invalid')->latest('id')->firstOrFail();
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.identity-confirm', $ticket), [
            'verification_event_id' => $invalidEvent->id, 'outcome' => 'confirmed',
        ])->assertSessionHasErrors('verification');
        $this->assertDatabaseCount('assistance_identity_confirmations', 0);
        $this->assertSame('30.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertNotNull($referenceResponse);
        $this->assertNotNull($invalidResponse);
    }

    public function test_negative_identity_result_missing_confirmation_and_superseded_ticket_never_authorize_handover(): void
    {
        $negativeTicket = $this->issueTicket($this->data['application'], $this->data['staff']);
        $token = Crypt::decryptString($negativeTicket->token_ciphertext);
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => $token])->assertRedirect();
        $event = AssistanceClaimTicketEvent::query()->where('ticket_id', $negativeTicket->id)->where('outcome', 'valid')->latest('id')->firstOrFail();
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.identity-confirm', $negativeTicket), [
            'verification_event_id' => $event->id, 'outcome' => 'not_confirmed',
        ])->assertRedirect();
        $failedConfirmation = AssistanceIdentityConfirmation::query()->where('verification_event_id', $event->id)->firstOrFail();
        $this->handoverCall($this->data['application'], $negativeTicket, $failedConfirmation, $this->data['batch'], 12)
            ->assertSessionHasErrors('identity_confirmation');

        $this->actingAs($this->data['staff'])->post(route('modules.aasms.handovers.distribute', [$this->data['application'], $negativeTicket]), [
            'identity_confirmation_id' => 999999, 'supply_batch_id' => $this->data['batch']->id,
            'quantity' => 12, 'confirm_physical_handover' => '1',
        ])->assertSessionHasErrors('identity_confirmation_id');

        $replacementApplication = $this->application('approved');
        [$oldTicket, $confirmation] = $this->issueAndConfirm($replacementApplication, $this->data['staff']);
        $replacement = app(\App\Services\AssistanceClaimTicketService::class)->reissue(
            $replacementApplication, $oldTicket, $this->data['staff'], 'Synthetic QA replacement scenario',
        );
        $this->assertSame('issued', $replacement->status);
        $this->handoverCall($replacementApplication, $oldTicket, $confirmation, $this->data['batch'], 12)
            ->assertSessionHasErrors('ticket');
        $this->assertSame('30.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('assistance_distributions', 0);
    }

    public function test_identity_confirmation_permission_is_independent_from_ticket_verification(): void
    {
        $ticket = $this->issueTicket($this->data['application'], $this->data['staff']);
        $token = Crypt::decryptString($ticket->token_ciphertext);
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => $token])->assertRedirect();
        $event = AssistanceClaimTicketEvent::query()->where('ticket_id', $ticket->id)->where('outcome', 'valid')->latest('id')->firstOrFail();
        $identityRole = $this->userWithPermissions([
            'modules.aasms.view', 'modules.aasms.claim_ticket_verify', 'modules.aasms.identity_confirm',
        ]);
        // A different employee cannot reuse another actor's successful token-check event.
        $this->actingAs($identityRole)->post(route('modules.aasms.claim-tickets.identity-confirm', $ticket), [
            'verification_event_id' => $event->id, 'outcome' => 'confirmed',
        ])->assertSessionHasErrors('verification');

        $verifyOnly = $this->userWithPermissions(['modules.aasms.view', 'modules.aasms.claim_ticket_verify']);
        $this->actingAs($verifyOnly)->post(route('modules.aasms.claim-tickets.identity-confirm', $ticket), [
            'verification_event_id' => $event->id, 'outcome' => 'confirmed',
        ])->assertForbidden();
        $this->assertDatabaseCount('assistance_identity_confirmations', 0);
    }

    public function test_application_ticket_and_confirmation_state_are_rechecked_for_all_nonapproved_and_terminal_states(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (['submitted', 'returned', 'rejected', 'completed'] as $status) {
            $application = $this->application('approved');
            [$ticket, $confirmation] = $this->issueAndConfirm($application, $this->data['staff']);
            $application->update(['status' => $status]);
            $this->handoverCall($application, $ticket, $confirmation, $this->data['batch'], 12)
                ->assertSessionHasErrors('application');
        }

        foreach (['revoked', 'expired'] as $terminal) {
            $application = $this->application('approved');
            [$ticket, $confirmation] = $this->issueAndConfirm($application, $this->data['staff']);
            if ($terminal === 'expired') {
                $ticket->update(['expires_at' => now()->subMinute()]);
            } else {
                $ticket->update(['status' => 'revoked', 'active_slot' => null, 'token_ciphertext' => null]);
            }
            $this->handoverCall($application, $ticket, $confirmation, $this->data['batch'], 12)
                ->assertSessionHasErrors('ticket');
        }

        $this->assertDatabaseCount('assistance_distributions', 0);
        $this->assertSame('30.00', (string) $this->data['batch']->fresh()->quantity_available);
    }

    public function test_wrong_application_wrong_confirmation_item_quantity_and_explicit_acknowledgment_fail_without_stock_change(): void
    {
        [$ticket, $confirmation] = $this->issueAndConfirm($this->data['application'], $this->data['staff']);
        $otherApplication = $this->application('approved');
        $this->handoverCall($otherApplication, $ticket, $confirmation, $this->data['batch'], 12)->assertSessionHasErrors('ticket');

        $otherItem = $this->newItem('QA-OTHER-SEED');
        $wrongBatch = $this->createBatch($otherItem, 50, 'QA-WRONG-ITEM');
        $this->handoverCall($this->data['application'], $ticket, $confirmation, $wrongBatch, 12)->assertSessionHasErrors('supply_batch_id');
        $this->handoverCall($this->data['application'], $ticket, $confirmation, $this->data['batch'], 13)->assertSessionHasErrors('quantity');

        $payload = $this->handoverPayload($confirmation, $this->data['batch'], 12);
        unset($payload['confirm_physical_handover']);
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.handovers.distribute', [$this->data['application'], $ticket]), $payload)
            ->assertSessionHasErrors('confirm_physical_handover');
        $this->assertSame('30.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_insufficient_stock_and_expired_batches_roll_back_all_handover_evidence(): void
    {
        [$ticket, $confirmation] = $this->issueAndConfirm($this->data['application'], $this->data['staff']);
        $insufficient = $this->secondBatch($this->data['item'], 5);
        $this->handoverCall($this->data['application'], $ticket, $confirmation, $insufficient, 12)->assertSessionHasErrors('quantity');

        $expired = $this->secondBatch($this->data['item'], 20, now()->subDay()->toDateString());
        $this->handoverCall($this->data['application'], $ticket, $confirmation, $expired, 12)->assertSessionHasErrors('supply_batch_id');
        $this->assertSame('30.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertSame('5.00', (string) $insufficient->fresh()->quantity_available);
        $this->assertSame('20.00', (string) $expired->fresh()->quantity_available);
        $this->assertSame('approved', $this->data['application']->fresh()->status);
        $this->assertSame('issued', $ticket->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('assistance_distributions', 0);
        $this->assertDatabaseCount('assistance_handover_acknowledgments', 0);
    }

    public function test_each_dual_module_and_literal_handover_permission_is_required_and_revocation_is_immediate(): void
    {
        [$ticket, $confirmation] = $this->issueAndConfirm($this->data['application'], $this->data['staff']);
        $withoutAisms = $this->userWithPermissions(array_values(array_diff($this->data['permissions'], ['modules.aisms.release'])));
        $this->actingAs($withoutAisms)->post(route('modules.aasms.handovers.distribute', [$this->data['application'], $ticket]), $this->handoverPayload($confirmation, $this->data['batch'], 12))->assertForbidden();

        $withoutAasms = $this->userWithPermissions(array_values(array_diff($this->data['permissions'], ['modules.aasms.distribute'])));
        $this->actingAs($withoutAasms)->post(route('modules.aasms.handovers.distribute', [$this->data['application'], $ticket]), $this->handoverPayload($confirmation, $this->data['batch'], 12))->assertForbidden();

        $withoutLiteral = $this->userWithPermissions(array_values(array_diff($this->data['permissions'], ['modules.aasms.handover_distribute'])));
        $this->actingAs($withoutLiteral)->post(route('modules.aasms.handovers.distribute', [$this->data['application'], $ticket]), $this->handoverPayload($confirmation, $this->data['batch'], 12))->assertForbidden();

        $this->data['role']->update(['permissions' => array_values(array_diff($this->data['permissions'], ['modules.aasms.handover_distribute']))]);
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.handovers.distribute', [$this->data['application'], $ticket]), $this->handoverPayload($confirmation, $this->data['batch'], 12))->assertForbidden();
        $this->assertSame('30.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_wildcard_does_not_grant_new_identity_or_handover_capabilities_and_get_never_redeems(): void
    {
        $wildcard = $this->userWithPermissions(['modules.aasms.*', 'modules.aisms.*']);
        $this->assertFalse($wildcard->canErp('modules.aasms.identity_confirm'));
        $this->assertFalse($wildcard->canErp('modules.aasms.handover_distribute'));
        $superRole = Role::create(['name' => 'QA super admin without handover grants', 'slug' => 'super_admin', 'permissions' => ['*'], 'is_active' => true]);
        $superUser = User::factory()->create();
        $superUser->roles()->attach($superRole);
        $this->assertFalse($superUser->canErp('modules.aasms.identity_confirm'));
        $this->assertFalse($superUser->canErp('modules.aasms.handover_distribute'));
        $ticket = $this->issueTicket($this->data['application'], $this->data['staff']);
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.verify-form'))->assertOk();
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.show', $ticket))->assertOk();
        $this->assertSame('issued', $ticket->fresh()->status);
        $this->assertSame('30.00', (string) $this->data['batch']->fresh()->quantity_available);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_failed_database_write_after_inventory_release_rolls_back_everything(): void
    {
        $this->withoutExceptionHandling();
        [$ticket, $confirmation] = $this->issueAndConfirm($this->data['application'], $this->data['staff']);
        DB::unprepared("CREATE TRIGGER qa_fail_handover_ack BEFORE INSERT ON assistance_handover_acknowledgments BEGIN SELECT RAISE(ABORT, 'synthetic QA failure'); END");

        try {
            $this->actingAs($this->data['staff'])->post(route('modules.aasms.handovers.distribute', [$this->data['application'], $ticket]), $this->handoverPayload($confirmation, $this->data['batch'], 12));
            $this->fail('The synthetic database trigger must fail the acknowledgment insert.');
        } catch (QueryException) {
            $this->assertSame('30.00', (string) $this->data['batch']->fresh()->quantity_available);
            $this->assertSame('issued', $ticket->fresh()->status);
            $this->assertSame('approved', $this->data['application']->fresh()->status);
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('assistance_distributions', 0);
            $this->assertDatabaseCount('assistance_handover_acknowledgments', 0);
            $this->assertDatabaseCount('notifications', 0);
        }
    }

    public function test_farmer_can_view_only_their_committed_acknowledgment_and_ticket_distribution(): void
    {
        [$ticket, $confirmation] = $this->issueAndConfirm($this->data['application'], $this->data['staff']);
        $this->postHandover($this->data['application'], $ticket, $confirmation, $this->data['batch'], 12);
        $ack = AssistanceHandoverAcknowledgment::sole();
        $this->actingAs($this->data['farmerUser'])->get(route('farmer.handovers.acknowledgment', $ack))
            ->assertOk()->assertSee('SYNTHETIC QA ONLY')->assertSee($ack->reference)->assertDontSee('synthetic_qa_match');
        $this->actingAs($this->data['farmerUser'])->get(route('farmer.claim-tickets.show', $ticket))
            ->assertOk()->assertSee('Distribution recorded')->assertSee('View / print distribution acknowledgment');

        $otherUser = User::factory()->create();
        $otherUser->roles()->attach($this->data['farmerRole']);
        Farmer::create(['user_id' => $otherUser->id, 'farmer_code' => 'QA-OTHER-OWNER', 'registration_status' => 'verified']);
        $this->actingAs($otherUser)->get(route('farmer.handovers.acknowledgment', $ack))->assertNotFound();
    }

    public function test_gate_rejects_operational_mysql_database_before_querying_or_attesting_it(): void
    {
        config([
            'app.env' => 'local',
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'agriconnect_erp',
            'database.connections.mysql.url' => null,
            'agricultural_claim_tickets.enabled' => true,
            'agricultural_claim_tickets.qa_mysql_database' => 'agriconnect_erp',
        ]);

        $this->assertFalse(app(\App\Services\ClaimTicketFeatureGate::class)->enabled());
        config(['database.default' => 'sqlite']);
    }

    private function fixture(array $permissions): array
    {
        $role = Role::create(['name' => 'Handover QA staff', 'slug' => 'handover_qa_'.str()->random(6), 'permissions' => $permissions, 'is_active' => true]);
        $staff = User::factory()->create();
        $staff->roles()->attach($role);
        $farmerRole = Role::create(['name' => 'Farmer handover QA', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $farmerUser = User::factory()->create();
        $farmerUser->roles()->attach($farmerRole);
        $farmer = Farmer::create(['user_id' => $farmerUser->id, 'farmer_code' => 'QA-HO-'.str()->upper(str()->random(6)), 'registration_status' => 'verified']);
        $program = AssistanceProgram::create(['name' => 'Synthetic QA assistance program', 'status' => 'open']);
        $item = $this->newItem('QA-HO-SEED');
        $location = InventoryLocation::query()->firstOrCreate(['name' => 'QA handover location'], ['is_active' => true]);
        $batch = SupplyBatch::create([
            'supply_item_id' => $item->id, 'location_id' => $location->id, 'batch_number' => 'QA-HO-BATCH-1',
            'received_on' => now()->toDateString(), 'expires_on' => now()->addYear()->toDateString(),
            'quantity_received' => 30, 'quantity_available' => 30, 'status' => 'available',
        ]);
        $application = AssistanceApplication::create([
            'program_id' => $program->id, 'farmer_id' => $farmer->id, 'purpose' => 'Synthetic QA only',
            'eligibility_passed' => true, 'status' => 'approved', 'approved_quantity' => 12,
            'approved_supply_item_id' => $item->id, 'allocation_confirmed_at' => now(),
            'application_reference' => 'QA-HO-APP-'.str()->upper(str()->random(6)),
        ]);

        return compact('role', 'staff', 'farmerRole', 'farmerUser', 'farmer', 'program', 'item', 'location', 'batch', 'application', 'permissions');
    }

    private function newItem(string $code): SupplyItem
    {
        $category = SupplyCategory::query()->firstOrCreate(['name' => 'QA handover supplies'], ['is_active' => true]);
        $unit = UnitOfMeasurement::query()->firstOrCreate(['name' => 'QA handover unit'], ['symbol' => 'qhu']);
        return SupplyItem::create(['category_id' => $category->id, 'unit_id' => $unit->id, 'code' => $code, 'name' => 'Synthetic QA seed pack', 'minimum_stock' => 0, 'status' => 'active']);
    }

    private function secondBatch(SupplyItem $item, float $quantity, ?string $expiresOn = null): SupplyBatch
    {
        return $this->createBatch($item, $quantity, 'QA-HO-BATCH-'.str()->upper(str()->random(5)), $expiresOn);
    }

    private function createBatch(SupplyItem $item, float $quantity, string $batchNumber, ?string $expiresOn = null): SupplyBatch
    {
        $location = InventoryLocation::query()->firstOrCreate(['name' => 'QA secondary location'], ['is_active' => true]);
        return SupplyBatch::create([
            'supply_item_id' => $item->id, 'location_id' => $location->id, 'batch_number' => $batchNumber,
            'received_on' => now()->subDay()->toDateString(), 'expires_on' => $expiresOn ?? now()->addYear()->toDateString(),
            'quantity_received' => $quantity, 'quantity_available' => $quantity, 'status' => 'available',
        ]);
    }

    private function application(string $status): AssistanceApplication
    {
        $ownerUser = User::factory()->create();
        $ownerUser->roles()->attach($this->data['farmerRole']);
        $owner = Farmer::create([
            'user_id' => $ownerUser->id,
            'farmer_code' => 'QA-HO-'.str()->upper(str()->random(6)),
            'registration_status' => 'verified',
        ]);

        return AssistanceApplication::create([
            'program_id' => $this->data['program']->id, 'farmer_id' => $owner->id,
            'purpose' => 'Synthetic QA '.$status, 'eligibility_passed' => true, 'status' => $status,
            'approved_quantity' => 12, 'approved_supply_item_id' => $this->data['item']->id,
            'allocation_confirmed_at' => now(), 'application_reference' => 'QA-HO-APP-'.str()->upper(str()->random(6)),
        ]);
    }

    private function issueTicket(AssistanceApplication $application, User $actor): AssistanceClaimTicket
    {
        $this->actingAs($actor)->post(route('modules.aasms.claim-tickets.issue', $application))->assertRedirect();
        return AssistanceClaimTicket::query()->where('application_id', $application->id)->latest('id')->firstOrFail();
    }

    private function issueAndConfirm(AssistanceApplication $application, User $actor): array
    {
        $ticket = $this->issueTicket($application, $actor);
        $token = Crypt::decryptString($ticket->token_ciphertext);
        $this->actingAs($actor)->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => 'AGC1:'.$token])
            ->assertRedirect()->assertSessionHas('claim_ticket_verification.outcome', 'valid');
        $event = AssistanceClaimTicketEvent::query()
            ->where('ticket_id', $ticket->id)->where('outcome', 'valid')->whereIn('verification_method', ['token', 'qr'])
            ->latest('id')->firstOrFail();
        $payload = [
            'verification_event_id' => $event->id, 'outcome' => 'confirmed',
        ];
        if (config('agricultural_claim_tickets.mode') === 'operational') {
            $payload['identity_attestation'] = '1';
        }
        $this->actingAs($actor)->post(route('modules.aasms.claim-tickets.identity-confirm', $ticket), $payload)->assertRedirect();
        $confirmation = AssistanceIdentityConfirmation::query()->where('verification_event_id', $event->id)->firstOrFail();
        return [$ticket, $confirmation];
    }

    private function handoverPayload(AssistanceIdentityConfirmation $confirmation, SupplyBatch $batch, float $quantity): array
    {
        return [
            'identity_confirmation_id' => $confirmation->id,
            'supply_batch_id' => $batch->id,
            'quantity' => number_format($quantity, 2, '.', ''),
            'confirm_physical_handover' => '1',
        ];
    }

    private function handoverCall(AssistanceApplication $application, AssistanceClaimTicket $ticket, AssistanceIdentityConfirmation $confirmation, SupplyBatch $batch, float $quantity)
    {
        return $this->actingAs($this->data['staff'])->post(
            route('modules.aasms.handovers.distribute', [$application, $ticket]),
            $this->handoverPayload($confirmation, $batch, $quantity),
        );
    }

    private function postHandover(AssistanceApplication $application, AssistanceClaimTicket $ticket, AssistanceIdentityConfirmation $confirmation, SupplyBatch $batch, float $quantity)
    {
        return $this->handoverCall($application, $ticket, $confirmation, $batch, $quantity)->assertRedirect();
    }

    private function userWithPermissions(array $permissions): User
    {
        $suffix = str()->random(8);
        $role = Role::create(['name' => 'Handover permission QA '.$suffix, 'slug' => 'handover_permission_'.$suffix, 'permissions' => $permissions, 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        return $user;
    }

    private function createApplicationAcknowledgment(AssistanceApplication $application): AssistanceApplicationAcknowledgment
    {
        return AssistanceApplicationAcknowledgment::create([
            'application_id' => $application->id,
            'application_reference' => $application->application_reference,
            'snapshot' => ['qa' => 'original submission acknowledgment'],
            'snapshot_sha256' => hash('sha256', 'qa-ack-'.$application->id),
            'generated_at' => now(),
        ]);
    }
}
