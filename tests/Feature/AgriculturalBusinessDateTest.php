<?php

namespace Tests\Feature;

use App\Models\AssistanceApplication;
use App\Models\AssistanceProgram;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use App\Services\AgriculturalBusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AgriculturalBusinessDateTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_business_date_tracks_philippine_midnight_0759_and_0800_without_changing_app_timezone(): void
    {
        $calendar = app(AgriculturalBusinessCalendar::class);
        $program = new AssistanceProgram([
            'name' => 'Local opening boundary',
            'status' => 'open',
            'opens_on' => '2026-11-01',
            'closes_on' => '2026-11-01',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-10-31 15:59:59', 'UTC'));
        $this->assertSame('2026-10-31', $calendar->today()->toDateString());
        $this->assertFalse($program->isAvailableOn($calendar->today()));
        $this->assertSame('upcoming', $program->availabilityStateOn($calendar->today()));

        $this->travelTo(CarbonImmutable::parse('2026-10-31 16:00:00', 'UTC'));
        $this->assertSame('2026-11-01', $calendar->today()->toDateString());
        $this->assertTrue($program->isAvailableOn($calendar->today()));

        $this->travelTo(CarbonImmutable::parse('2026-10-31 23:59:00', 'UTC'));
        $this->assertSame('2026-11-01', $calendar->today()->toDateString());
        $this->assertTrue($program->isAvailableOn($calendar->today()));

        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC'));
        $this->assertSame('2026-11-01', $calendar->today()->toDateString());
        $this->assertTrue($program->isAvailableOn($calendar->today()));
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_program_closing_date_remains_inclusive_until_midnight_in_philippine_time(): void
    {
        $calendar = app(AgriculturalBusinessCalendar::class);
        $program = new AssistanceProgram([
            'name' => 'Local closing boundary',
            'status' => 'open',
            'opens_on' => '2026-10-01',
            'closes_on' => '2026-10-31',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-10-31 15:59:59', 'UTC'));
        $this->assertSame('2026-10-31', $calendar->today()->toDateString());
        $this->assertTrue($program->isAvailableOn($calendar->today()));

        $this->travelTo(CarbonImmutable::parse('2026-10-31 16:00:00', 'UTC'));
        $this->assertSame('2026-11-01', $calendar->today()->toDateString());
        $this->assertFalse($program->isAvailableOn($calendar->today()));
        $this->assertSame('ended', $program->availabilityStateOn($calendar->today()));
    }

    public function test_farmer_home_catalog_details_and_apply_share_the_local_opening_and_closing_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 16:00:00', 'UTC'));
        $farmer = $this->farmer();
        $openingAndClosingToday = $this->program('Opens and closes today', '2026-11-01', '2026-11-01');
        $withoutOpeningDate = $this->program('No opening date', null, '2026-11-01');
        $withoutClosingDate = $this->program('No closing date', '2026-10-01', null);
        $upcoming = $this->program('Opens tomorrow', '2026-11-02', '2026-11-03');
        $ended = $this->program('Closed yesterday', '2026-10-01', '2026-10-31');
        $unpublished = $this->program('Unpublished program', '2026-11-01', '2026-11-01', 'draft');

        $this->actingAs($farmer['user'])->get('/farmer')->assertOk()
            ->assertSee($openingAndClosingToday->name)
            ->assertSee($withoutOpeningDate->name)
            ->assertSee($withoutClosingDate->name)
            ->assertDontSee($upcoming->name)
            ->assertDontSee($ended->name)
            ->assertDontSee($unpublished->name);

        foreach (['/farmer/services', '/farmer/assistance'] as $path) {
            $this->actingAs($farmer['user'])->get($path)
                ->assertOk()
                ->assertSee($openingAndClosingToday->name)
                ->assertSee($withoutOpeningDate->name)
                ->assertSee($withoutClosingDate->name)
                ->assertDontSee($upcoming->name)
                ->assertDontSee($ended->name)
                ->assertDontSee($unpublished->name);
        }

        $this->actingAs($farmer['user'])->get('/farmer/services?availability=upcoming')
            ->assertOk()->assertSee($upcoming->name)->assertDontSee($openingAndClosingToday->name);
        $this->actingAs($farmer['user'])->get('/farmer/services?availability=ended')
            ->assertOk()->assertSee($ended->name)->assertDontSee($openingAndClosingToday->name);
        $this->actingAs($farmer['user'])->get('/farmer/programs/'.$unpublished->id)->assertNotFound();

        $this->actingAs($farmer['user'])->get('/farmer/programs/'.$openingAndClosingToday->id)
            ->assertOk()->assertSee('Accepting applications')->assertSee('Submit application');

        $this->actingAs($farmer['user'])
            ->from('/farmer/programs/'.$openingAndClosingToday->id)
            ->post(route('farmer.assistance.apply', $openingAndClosingToday), ['purpose' => 'Planting for the next season'])
            ->assertRedirect('/farmer/programs/'.$openingAndClosingToday->id)
            ->assertSessionHas('status', 'Assistance application submitted.');

        $application = AssistanceApplication::query()->where('program_id', $openingAndClosingToday->id)->firstOrFail();
        $this->assertDatabaseHas('assistance_applications', [
            'id' => $application->id,
            'farmer_id' => $farmer['farmer']->id,
            'status' => 'submitted',
            'eligibility_passed' => true,
        ]);
        $this->assertDatabaseHas('assistance_status_histories', [
            'application_id' => $application->id,
            'to_status' => 'submitted',
        ]);
    }

    public function test_direct_apply_and_resubmit_are_rejected_after_the_local_closing_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 16:00:00', 'UTC'));
        $farmer = $this->farmer();
        $program = $this->program('Ended yesterday', '2026-10-01', '2026-10-31');

        $this->actingAs($farmer['user'])->get('/farmer/programs/'.$program->id)
            ->assertOk()->assertSee('Application period ended')->assertDontSee('name="purpose"', false);

        $this->actingAs($farmer['user'])
            ->from('/farmer/programs/'.$program->id)
            ->post(route('farmer.assistance.apply', $program), ['purpose' => 'Late application'])
            ->assertRedirect('/farmer/programs/'.$program->id)
            ->assertSessionHasErrors('program');
        $this->assertDatabaseMissing('assistance_applications', [
            'program_id' => $program->id,
            'farmer_id' => $farmer['farmer']->id,
        ]);

        $application = AssistanceApplication::create([
            'program_id' => $program->id,
            'farmer_id' => $farmer['farmer']->id,
            'status' => 'returned',
            'review_notes' => 'Please update your purpose.',
        ]);
        $application->history()->create([
            'from_status' => 'submitted',
            'to_status' => 'returned',
            'changed_by' => $farmer['user']->id,
            'notes' => 'Please update your purpose.',
        ]);

        $this->actingAs($farmer['user'])
            ->from('/farmer/applications/'.$application->id)
            ->post(route('farmer.assistance.resubmit', $application), ['purpose' => 'Updated purpose'])
            ->assertRedirect('/farmer/applications/'.$application->id)
            ->assertSessionHasErrors('program');

        $this->assertDatabaseHas('assistance_applications', ['id' => $application->id, 'status' => 'returned']);
        $this->assertDatabaseCount('assistance_status_histories', 1);
    }

    public function test_returned_application_can_be_resubmitted_on_its_local_opening_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 16:00:00', 'UTC'));
        $farmer = $this->farmer();
        $program = $this->program('Resubmission opens today', '2026-11-01', '2026-11-01');
        $application = AssistanceApplication::create([
            'program_id' => $program->id,
            'farmer_id' => $farmer['farmer']->id,
            'status' => 'returned',
            'review_notes' => 'Please clarify your purpose.',
        ]);
        $application->history()->create([
            'from_status' => 'submitted',
            'to_status' => 'returned',
            'changed_by' => $farmer['user']->id,
            'notes' => 'Please clarify your purpose.',
        ]);

        $this->actingAs($farmer['user'])
            ->post(route('farmer.assistance.resubmit', $application), ['purpose' => 'Updated purpose'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Application resubmitted for MAO review.');

        $this->assertDatabaseHas('assistance_applications', [
            'id' => $application->id,
            'status' => 'submitted',
            'purpose' => 'Updated purpose',
            'eligibility_passed' => true,
        ]);
        $this->assertDatabaseHas('assistance_status_histories', [
            'application_id' => $application->id,
            'from_status' => 'returned',
            'to_status' => 'submitted',
        ]);
    }

    /** @return array{user: User, farmer: Farmer} */
    private function farmer(): array
    {
        $role = Role::firstOrCreate(['slug' => 'farmer'], [
            'name' => 'Farmer',
            'permissions' => ['farmer.assistance.apply'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $farmer = Farmer::create([
            'user_id' => $user->id,
            'farmer_code' => 'F-'.fake()->unique()->numerify('######'),
            'barangay' => 'Poblacion',
            'registration_status' => 'verified',
        ]);
        Farm::create([
            'farmer_id' => $farmer->id,
            'name' => 'Rice Farm',
            'barangay' => 'Poblacion',
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
        ]);

        return compact('user', 'farmer');
    }

    private function program(string $name, ?string $opensOn, ?string $closesOn, string $status = 'open'): AssistanceProgram
    {
        return AssistanceProgram::create([
            'name' => $name,
            'status' => $status,
            'opens_on' => $opensOn,
            'closes_on' => $closesOn,
        ]);
    }
}
