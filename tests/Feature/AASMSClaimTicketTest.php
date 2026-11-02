<?php

namespace Tests\Feature;

use App\Models\AssistanceApplication;
use App\Models\AssistanceClaimTicket;
use App\Models\AssistanceProgram;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\SupplyCategory;
use App\Models\SupplyItem;
use App\Models\UnitOfMeasurement;
use App\Models\User;
use App\Services\AssistanceClaimTicketService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AASMSClaimTicketTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agricultural_claim_tickets.enabled' => true,
            'agricultural_claim_tickets.qa_sqlite_path' => storage_path('app/qa/agriconnect-phase6d1-qa.sqlite'),
            'agricultural_claim_tickets.qa_mysql_database' => null,
            'agricultural_claim_tickets.validity_days' => null,
            'agricultural_claim_tickets.pickup_instructions' => null,
            'agricultural_claim_tickets.office_name' => null,
            'agricultural_claim_tickets.office_address' => null,
        ]);

        $staffRole = Role::create([
            'name' => 'Claim Ticket MAO QA',
            'slug' => 'claim_ticket_mao_qa',
            'permissions' => [
                'modules.aasms.view',
                'modules.aasms.claim_ticket_view',
                'modules.aasms.claim_ticket_issue',
                'modules.aasms.claim_ticket_verify',
                'modules.aasms.claim_ticket_revoke',
                'modules.aasms.claim_ticket_reissue',
                'modules.aasms.claim_ticket_history',
            ],
            'is_active' => true,
        ]);
        $this->data['staff'] = User::factory()->create();
        $this->data['staff']->roles()->attach($staffRole);

        $farmerRole = Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $this->data['farmerUser'] = User::factory()->create();
        $this->data['farmerUser']->roles()->attach($farmerRole);
        $this->data['farmer'] = Farmer::create([
            'user_id' => $this->data['farmerUser']->id,
            'farmer_code' => 'QA-CLAIM-001',
            'registration_status' => 'verified',
        ]);
        $this->data['program'] = AssistanceProgram::create(['name' => 'Synthetic QA Seed Program', 'status' => 'open']);
        $category = SupplyCategory::create(['name' => 'QA Seeds', 'is_active' => true]);
        $unit = UnitOfMeasurement::create(['name' => 'QA Kilogram', 'symbol' => 'qkg']);
        $this->data['item'] = SupplyItem::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'QA-CLAIM-SEED',
            'name' => 'Synthetic QA Seed Pack',
            'minimum_stock' => 0,
            'status' => 'active',
        ]);
        $this->data['application'] = $this->newApplication();
    }

    public function test_authorized_staff_issues_ticket_without_inventory_or_distribution_changes(): void
    {
        $beforeMovements = DB::table('inventory_movements')->count();
        $beforeDistributions = DB::table('assistance_distributions')->count();

        $response = $this->actingAs($this->data['staff'])
            ->post(route('modules.aasms.claim-tickets.issue', $this->data['application']))
            ->assertRedirect();

        $ticket = AssistanceClaimTicket::sole();
        $this->assertSame('issued', $ticket->status);
        $this->assertSame(1, (int) $ticket->active_slot);
        $this->assertSame((int) $this->data['item']->id, (int) $ticket->approved_supply_item_id);
        $this->assertSame('12.00', (string) $ticket->approved_quantity);
        $this->assertSame($this->data['staff']->id, $ticket->issued_by);
        $this->assertDatabaseHas('assistance_claim_ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'issued']);
        $this->assertSame($beforeMovements, DB::table('inventory_movements')->count());
        $this->assertSame($beforeDistributions, DB::table('assistance_distributions')->count());
        $this->assertSame('approved', $this->data['application']->fresh()->status);
        $this->assertStringNotContainsString('AGC1:', $response->headers->get('Location', ''));
    }

    public function test_only_approved_verified_allocated_and_undistributed_applications_are_eligible(): void
    {
        foreach (['submitted', 'returned', 'rejected', 'completed'] as $status) {
            $farmer = Farmer::create([
                'user_id' => User::factory()->create()->id,
                'farmer_code' => 'QA-CLAIM-'.strtoupper($status),
                'registration_status' => 'verified',
            ]);
            $application = $this->newApplication($status, true, $farmer->id);
            $this->actingAs($this->data['staff'])
                ->post(route('modules.aasms.claim-tickets.issue', $application))
                ->assertSessionHasErrors('application');
        }

        $missingFarmer = Farmer::create([
            'user_id' => User::factory()->create()->id,
            'farmer_code' => 'QA-CLAIM-NO-ALLOCATION',
            'registration_status' => 'verified',
        ]);
        $missingAllocation = $this->newApplication('approved', false, $missingFarmer->id);
        $this->actingAs($this->data['staff'])
            ->post(route('modules.aasms.claim-tickets.issue', $missingAllocation))
            ->assertSessionHasErrors('application');

        $unverified = Farmer::create([
            'user_id' => User::factory()->create()->id,
            'farmer_code' => 'QA-CLAIM-PENDING',
            'registration_status' => 'pending',
        ]);
        $pendingFarmerApplication = $this->newApplication('approved', true, $unverified->id);
        $this->actingAs($this->data['staff'])
            ->post(route('modules.aasms.claim-tickets.issue', $pendingFarmerApplication))
            ->assertSessionHasErrors('application');

        $this->assertDatabaseCount('assistance_claim_tickets', 0);
    }

    public function test_duplicate_first_issue_is_rejected_and_database_allows_only_one_active_slot(): void
    {
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.issue', $this->data['application']))->assertRedirect();
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.issue', $this->data['application']))->assertSessionHasErrors('application');
        $this->assertDatabaseCount('assistance_claim_tickets', 1);

        $ticket = AssistanceClaimTicket::firstOrFail();
        try {
            AssistanceClaimTicket::create([
                'application_id' => $ticket->application_id,
                'ticket_reference' => 'AGC-DUPLICATE-QA',
                'token_hash' => hash('sha256', 'unique-test-token'),
                'status' => 'issued',
                'active_slot' => 1,
                'approved_supply_item_id' => $ticket->approved_supply_item_id,
                'approved_quantity' => $ticket->approved_quantity,
                'allocation_confirmed_at' => $ticket->allocation_confirmed_at,
                'allocation_snapshot_sha256' => $ticket->allocation_snapshot_sha256,
                'issued_by' => $ticket->issued_by,
                'issued_at' => now(),
            ]);
            $this->fail('The application/active-slot unique constraint must reject a second active ticket.');
        } catch (QueryException) {
            $this->assertDatabaseCount('assistance_claim_tickets', 1);
        }
    }

    public function test_application_and_ticket_history_rows_are_restrictively_protected_from_deletion(): void
    {
        $ticket = $this->issueTicket();

        try {
            $this->data['application']->delete();
            $this->fail('A ticket must prevent deletion of its linked application.');
        } catch (QueryException) {
            $this->assertDatabaseHas('assistance_applications', ['id' => $this->data['application']->id]);
        }

        try {
            $ticket->delete();
            $this->fail('Ticket event history must prevent deletion of the ticket.');
        } catch (QueryException) {
            $this->assertDatabaseHas('assistance_claim_tickets', ['id' => $ticket->id]);
            $this->assertDatabaseHas('assistance_claim_ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'issued']);
        }

        $event = $ticket->events()->where('event_type', 'issued')->firstOrFail();
        try {
            $event->update(['outcome' => 'tampered']);
            $this->fail('Ticket audit events must not be editable.');
        } catch (\LogicException $exception) {
            $this->assertSame('Claim-ticket audit events are append-only.', $exception->getMessage());
        }
        try {
            $event->delete();
            $this->fail('Ticket audit events must not be deletable through Eloquent.');
        } catch (\LogicException $exception) {
            $this->assertSame('Claim-ticket audit events are append-only.', $exception->getMessage());
        }
        $this->assertDatabaseHas('assistance_claim_ticket_events', ['id' => $event->id, 'outcome' => 'success']);
    }

    public function test_broad_aasms_wildcard_does_not_imply_ticket_issue_or_verification(): void
    {
        $wildcardRole = Role::create(['name' => 'Generic AASMS QA', 'slug' => 'generic_aasms_qa', 'permissions' => ['modules.aasms.*'], 'is_active' => true]);
        $generic = User::factory()->create();
        $generic->roles()->attach($wildcardRole);
        $this->assertFalse($generic->canErp('modules.aasms.claim_ticket_issue'));
        $this->assertFalse($generic->canErp('modules.aasms.claim_ticket_verify'));

        $this->actingAs($generic)->post(route('modules.aasms.claim-tickets.issue', $this->data['application']))->assertForbidden();
        $this->actingAs($generic)->get(route('modules.aasms.claim-tickets.index'))->assertForbidden();
        $this->assertDatabaseCount('assistance_claim_tickets', 0);
    }

    public function test_auditor_exact_view_grants_are_read_only_and_never_expose_raw_token_fields(): void
    {
        $ticket = $this->issueTicket();
        $auditorRole = Role::create([
            'name' => 'Ticket Read-only Auditor QA',
            'slug' => 'ticket_auditor_qa',
            'permissions' => ['modules.aasms.view', 'modules.aasms.claim_ticket_view', 'modules.aasms.claim_ticket_history'],
            'is_active' => true,
        ]);
        $auditor = User::factory()->create();
        $auditor->roles()->attach($auditorRole);

        $this->assertTrue($auditor->canErp('modules.aasms.claim_ticket_view'));
        $this->assertTrue($auditor->canErp('modules.aasms.claim_ticket_history'));
        $this->assertFalse($auditor->canErp('modules.aasms.claim_ticket_verify'));
        $this->assertFalse($auditor->canErp('modules.aasms.claim_ticket_issue'));
        $this->actingAs($auditor)->get(route('modules.aasms.claim-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Immutable ticket history')
            ->assertDontSee($ticket->token_hash)
            ->assertDontSee($ticket->token_ciphertext);
        $this->actingAs($auditor)->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => 'x'])
            ->assertForbidden();
    }

    public function test_unknown_verification_input_is_not_flushed_to_session_or_audit_metadata(): void
    {
        $secretLikeInput = bin2hex(random_bytes(32));
        $response = $this->actingAs($this->data['staff'])
            ->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => $secretLikeInput])
            ->assertRedirect(route('modules.aasms.claim-tickets.verify-form'))
            ->assertSessionHas('claim_ticket_verification.outcome', 'invalid');

        $response->assertSessionMissingInput('ticket_input');
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.verify-form'))
            ->assertOk()
            ->assertSee('Token invalid');
        $event = DB::table('assistance_claim_ticket_events')->where('reason_code', 'invalid_token')->first();
        $this->assertNotNull($event);
        $this->assertNull($event->ticket_id);
        $this->assertNull($event->metadata);
        $this->assertStringNotContainsString($secretLikeInput, json_encode($event));
    }

    public function test_farmer_can_only_view_own_ticket_and_private_qr_has_no_personal_data_or_secret_url(): void
    {
        $ticket = $this->issueTicket();
        $ownerResponse = $this->actingAs($this->data['farmerUser'])
            ->get(route('farmer.claim-tickets.show', $ticket))
            ->assertOk()
            ->assertSee($ticket->ticket_reference)
            ->assertSee('not redeemable');

        $this->assertStringNotContainsString($ticket->token_hash, $ownerResponse->getContent());
        $this->assertStringNotContainsString($ticket->token_ciphertext, $ownerResponse->getContent());
        $qrUrl = route('farmer.claim-tickets.qr', $ticket);
        $this->assertStringNotContainsString('token=', $qrUrl);
        $qr = $this->get($qrUrl)->assertOk()->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');
        $this->assertStringNotContainsString($this->data['farmerUser']->name, $qr->getContent());
        $this->assertStringNotContainsString($this->data['application']->application_reference ?? 'QA-PRIVATE-APPLICATION-REFERENCE', $qr->getContent());
        $this->assertStringNotContainsString('AGC1:', $qr->getContent());
        $this->get(route('farmer.claim-tickets.print', $ticket))->assertOk()->assertSee('window.print()', false);

        $otherUser = User::factory()->create();
        $otherUser->roles()->attach(Role::where('slug', 'farmer')->firstOrFail());
        Farmer::create(['user_id' => $otherUser->id, 'farmer_code' => 'QA-CLAIM-OTHER', 'registration_status' => 'verified']);
        $this->actingAs($otherUser)->get(route('farmer.claim-tickets.show', $ticket))->assertNotFound();
        $this->actingAs($otherUser)->get($qrUrl)->assertNotFound();
    }

    public function test_qr_token_is_256_bit_random_hashed_at_rest_encrypted_for_owner_regeneration_and_never_in_url(): void
    {
        $ticket = $this->issueTicket();
        $token = Crypt::decryptString($ticket->token_ciphertext);
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $token);
        $this->assertSame(hash('sha256', 'agriconnect-claim-ticket-v1:'.$token), $ticket->token_hash);
        $this->assertNotSame($token, $ticket->token_hash);
        $this->assertStringNotContainsString($token, route('farmer.claim-tickets.show', $ticket));
        $this->assertStringNotContainsString($token, route('farmer.claim-tickets.qr', $ticket));
    }

    public function test_token_verification_and_manual_reference_lookup_are_read_only_and_audited(): void
    {
        $ticket = $this->issueTicket();
        $token = Crypt::decryptString($ticket->token_ciphertext);
        $movementCount = DB::table('inventory_movements')->count();
        $distributionCount = DB::table('assistance_distributions')->count();

        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => 'AGC1:'.$token])
            ->assertRedirect(route('modules.aasms.claim-tickets.verify-form'))
            ->assertSessionHas('claim_ticket_verification.outcome', 'valid');
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.verify-form'))
            ->assertOk()
            ->assertSee('Token valid')
            ->assertSee('identity not confirmed')
            ->assertSee($this->data['program']->name)
            ->assertSee($this->data['item']->name)
            ->assertSee('12.00')
            ->assertSee('not authorization to hand over supplies');
        $this->assertDatabaseHas('assistance_claim_ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'verification_attempt', 'outcome' => 'valid']);

        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => $ticket->ticket_reference])
            ->assertSessionHas('claim_ticket_verification.outcome', 'reference_only')
            ->assertSessionHas('claim_ticket_verification.method', 'reference');
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.verify-form'))
            ->assertOk()
            ->assertSee('Reference located')
            ->assertSee('token not checked')
            ->assertSee('A reference is not a secret credential')
            ->assertSee($this->data['item']->name);
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => 'AGC-NOT-A-REAL-REF'])
            ->assertSessionHas('claim_ticket_verification.outcome', 'not_found');
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.verify-form'))
            ->assertOk()
            ->assertSee('Reference not found')
            ->assertSee('A reference is not a secret credential');
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.verify'))->assertOk()->assertSee('Validate a ticket credential');

        $this->assertSame('issued', $ticket->fresh()->status);
        $this->assertDatabaseCount('assistance_distributions', $distributionCount);
        $this->assertDatabaseCount('inventory_movements', $movementCount);
        $this->assertDatabaseCount('assistance_status_histories', 0);
    }

    public function test_verification_secret_is_not_accepted_from_a_query_string(): void
    {
        $ticket = $this->issueTicket();
        $token = Crypt::decryptString($ticket->token_ciphertext);

        $this->actingAs($this->data['staff'])
            ->post(route('modules.aasms.claim-tickets.verify').'?ticket_input='.rawurlencode('AGC1:'.$token))
            ->assertSessionHasErrors('ticket_input')
            ->assertSessionMissingInput('ticket_input');

        $this->assertDatabaseMissing('assistance_claim_ticket_events', [
            'ticket_id' => $ticket->id,
            'event_type' => 'verification_attempt',
        ]);
    }

    public function test_revoke_and_reissue_preserve_history_and_invalidate_the_old_token(): void
    {
        $ticket = $this->issueTicket();
        $oldToken = Crypt::decryptString($ticket->token_ciphertext);
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.revoke', $ticket), ['reason' => 'Synthetic QA reissue test'])->assertRedirect();
        $this->assertSame('revoked', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->token_ciphertext);
        $this->assertNull($ticket->fresh()->active_slot);

        $replacement = app(AssistanceClaimTicketService::class)->reissue(
            $this->data['application'], $ticket->fresh(), $this->data['staff'], 'Synthetic QA replacement reason',
        );
        $this->assertSame($ticket->id, $replacement->supersedes_ticket_id);
        $this->assertSame('issued', $replacement->status);
        $this->assertSame(1, (int) $replacement->active_slot);
        $this->assertSame('revoked', $ticket->fresh()->status);
        $this->assertDatabaseHas('assistance_claim_ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'revoked']);
        $this->assertDatabaseHas('assistance_claim_ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'superseded']);
        $this->assertDatabaseHas('assistance_claim_ticket_events', ['ticket_id' => $replacement->id, 'event_type' => 'reissued']);
        $this->actingAs($this->data['farmerUser'])->get(route('farmer.claim-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Replacement history')
            ->assertSee($replacement->ticket_reference)
            ->assertSee('cannot be used');
        $this->actingAs($this->data['farmerUser'])->get(route('farmer.claim-tickets.print', $replacement))
            ->assertOk()
            ->assertSee('Expires')
            ->assertSee('No expiry configured')
            ->assertSee('Lost or damaged ticket?')
            ->assertSee('NOT REDEEMABLE');
        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => 'AGC1:'.$oldToken])
            ->assertSessionHas('claim_ticket_verification.outcome', 'revoked');
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.verify-form'))
            ->assertOk()
            ->assertSee('Ticket revoked or superseded');
        $this->assertSame(2, AssistanceClaimTicket::where('application_id', $this->data['application']->id)->count());
        $this->assertSame(1, AssistanceClaimTicket::where('application_id', $this->data['application']->id)->where('active_slot', 1)->count());
    }

    public function test_expiry_is_enforced_on_validation_and_expired_ticket_can_be_reissued(): void
    {
        config(['agricultural_claim_tickets.validity_days' => 1]);
        $ticket = $this->issueTicket();
        $token = Crypt::decryptString($ticket->token_ciphertext);
        $this->travel(2)->days();

        $this->actingAs($this->data['staff'])->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => 'AGC1:'.$token])
            ->assertSessionHas('claim_ticket_verification.outcome', 'expired');
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.verify-form'))
            ->assertOk()
            ->assertSee('Ticket expired');
        $this->assertSame('expired', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->active_slot);
        $this->assertNull($ticket->fresh()->token_ciphertext);

        $replacement = app(AssistanceClaimTicketService::class)->reissue(
            $this->data['application'], $ticket->fresh(), $this->data['staff'], 'Synthetic QA expired ticket replacement',
        );
        $this->assertSame('issued', $replacement->status);
        $this->assertSame('expired', $ticket->fresh()->status);
        $this->assertDatabaseHas('assistance_claim_ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'expired']);
    }

    public function test_allocation_change_invalidates_old_ticket_and_blocks_silent_reissue(): void
    {
        $ticket = $this->issueTicket();
        $this->data['application']->update(['approved_quantity' => 13]);
        $token = Crypt::decryptString($ticket->token_ciphertext);

        $result = app(AssistanceClaimTicketService::class)->verify($token, $this->data['staff']);
        $this->assertSame('approval_changed', $result['outcome']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(AssistanceClaimTicketService::class)->reissue($this->data['application'], $ticket, $this->data['staff'], 'Do not silently change allocation');
    }

    public function test_ticket_feature_is_disabled_by_default_and_get_requests_do_not_verify(): void
    {
        config(['agricultural_claim_tickets.enabled' => false]);
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.index'))->assertNotFound();
        config(['agricultural_claim_tickets.enabled' => true]);
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.verify-form'))->assertOk()->assertSee('Validate a ticket credential');
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.show', 999).'/redeem')->assertNotFound();
        $this->assertDatabaseCount('assistance_claim_tickets', 0);
        $this->assertDatabaseCount('assistance_claim_ticket_events', 0);
    }

    public function test_validation_route_is_permission_gated_and_rate_limited(): void
    {
        $verifyOnlyRole = Role::create([
            'name' => 'Ticket Verify Only QA',
            'slug' => 'ticket_verify_only_qa',
            'permissions' => ['modules.aasms.view', 'modules.aasms.claim_ticket_verify'],
            'is_active' => true,
        ]);
        $verifyOnly = User::factory()->create();
        $verifyOnly->roles()->attach($verifyOnlyRole);
        $this->actingAs($verifyOnly)->get(route('modules.aasms.claim-tickets.verify-form'))->assertOk();

        $viewerRole = Role::create([
            'name' => 'Ticket View Only QA',
            'slug' => 'ticket_view_only_qa',
            'permissions' => ['modules.aasms.view', 'modules.aasms.claim_ticket_view'],
            'is_active' => true,
        ]);
        $viewer = User::factory()->create();
        $viewer->roles()->attach($viewerRole);
        $this->actingAs($viewer)->get(route('modules.aasms.claim-tickets.verify-form'))->assertForbidden();

        $this->actingAs($this->data['staff']);
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => 'not-a-valid-token-'.$attempt])->assertRedirect();
        }
        $this->post(route('modules.aasms.claim-tickets.verify'), ['ticket_input' => 'eleventh-attempt'])
            ->assertTooManyRequests();
    }

    public function test_staff_dashboard_link_and_ticket_views_use_real_authenticated_records(): void
    {
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.dashboard'))->assertOk()->assertSee(route('modules.aasms.claim-tickets.index'), false);
        $ticket = $this->issueTicket();
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.index'))->assertOk()->assertSee($ticket->ticket_reference)->assertSee(route('modules.aasms.claim-tickets.verify-form'), false);
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.verify-form'))->assertOk()->assertSee('no camera scanner');
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.show', $ticket))->assertOk()->assertSee('Immutable ticket history')->assertDontSee($ticket->token_hash);
        $this->actingAs($this->data['farmerUser'])->get(route('farmer.applications.show', $this->data['application']))->assertOk()->assertSee($ticket->ticket_reference);
        $this->actingAs($this->data['farmerUser'])->get(route('farmer.claim-tickets.print', $ticket))->assertOk()->assertSee('No expiry configured')->assertSee('Lost or damaged ticket?');
        $this->actingAs($this->data['staff'])->get(route('modules.aasms.claim-tickets.show', $ticket).'/redeem')->assertNotFound();
    }

    private function issueTicket(): AssistanceClaimTicket
    {
        $this->actingAs($this->data['staff'])
            ->post(route('modules.aasms.claim-tickets.issue', $this->data['application']))
            ->assertRedirect();

        return AssistanceClaimTicket::query()->latest('id')->firstOrFail();
    }

    private function newApplication(string $status = 'approved', bool $allocation = true, ?int $farmerId = null): AssistanceApplication
    {
        return AssistanceApplication::create([
            'program_id' => $this->data['program']->id,
            'farmer_id' => $farmerId ?? $this->data['farmer']->id,
            'status' => $status,
            'eligibility_passed' => true,
            'approved_quantity' => $allocation ? 12 : null,
            'approved_supply_item_id' => $allocation ? $this->data['item']->id : null,
            'allocation_confirmed_at' => $allocation ? now() : null,
        ]);
    }
}
