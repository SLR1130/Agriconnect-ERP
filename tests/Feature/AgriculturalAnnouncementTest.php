<?php

namespace Tests\Feature;

use App\Models\AgriculturalAnnouncement;
use App\Models\AssistanceProgram;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgriculturalAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $roleSlug, array $permissions): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => $roleSlug, 'permissions' => $permissions, 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        if ($roleSlug === 'farmer') {
            Farmer::create(['user_id' => $user->id, 'farmer_code' => 'NOTICE-'.$user->id, 'registration_status' => 'pending']);
        }

        return $user;
    }

    public function test_draft_publication_notification_and_archive_are_scoped(): void
    {
        $staff = $this->account('mao_admin', ['modules.aasms.view', 'modules.aasms.create', 'modules.aasms.update']);
        $farmer = $this->account('farmer', []);
        $outsider = $this->account('authorized_viewer', ['modules.aasms.view']);
        $this->actingAs($staff)->post('/modules/aasms/announcements', ['title' => 'Seed service notice', 'body' => 'Applications open next week.'])->assertRedirect();
        $notice = AgriculturalAnnouncement::firstOrFail();
        $this->assertSame('draft', $notice->status);
        $this->actingAs($farmer)->get('/farmer/announcements')->assertOk()->assertDontSee('Seed service notice');
        $this->actingAs($farmer)->get('/farmer/announcements/'.$notice->id)->assertNotFound();
        $this->actingAs($outsider)->post('/modules/aasms/announcements/'.$notice->id.'/publish')->assertForbidden();
        $this->actingAs($staff)->post('/modules/aasms/announcements/'.$notice->id.'/publish')->assertRedirect();
        $this->actingAs($farmer)->get('/farmer/announcements')->assertOk()->assertSee('Seed service notice');
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($staff)->post('/modules/aasms/announcements/'.$notice->id.'/publish')->assertSessionHasErrors('announcement');
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($staff)->post('/modules/aasms/announcements/'.$notice->id.'/archive')->assertRedirect();
        $this->actingAs($farmer)->get('/farmer/announcements/'.$notice->id)->assertNotFound();
        $this->actingAs($farmer)->get('/modules/aasms/announcements')->assertForbidden();
    }

    public function test_expired_notice_is_not_visible_to_farmers(): void
    {
        $staff = $this->account('mao_admin', ['modules.aasms.view', 'modules.aasms.create', 'modules.aasms.update']);
        $farmer = $this->account('farmer', []);
        $this->actingAs($staff)->post('/modules/aasms/announcements', [
            'title' => 'Time limited advisory', 'body' => 'Available this week.', 'expires_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect();
        $notice = AgriculturalAnnouncement::firstOrFail();
        $this->actingAs($staff)->post('/modules/aasms/announcements/'.$notice->id.'/publish')->assertRedirect();
        $this->actingAs($farmer)->get('/farmer/announcements')->assertOk()->assertSee('Time limited advisory');

        $this->travel(2)->days();
        $this->actingAs($farmer)->get('/farmer/announcements')->assertOk()->assertDontSee('Time limited advisory');
        $this->actingAs($farmer)->get('/farmer/announcements/'.$notice->id)->assertNotFound();
    }

    public function test_program_creation_is_private_until_authorized_publication(): void
    {
        $staff = $this->account('mao_admin', ['modules.aasms.view', 'modules.aasms.create', 'modules.aasms.update']);
        $farmer = $this->account('farmer', []);
        $this->actingAs($staff)->post('/modules/aasms/programs', ['name' => 'Private Rice Program', 'description' => 'Seed support.', 'opens_on' => '2026-10-01', 'closes_on' => '2027-10-01'])->assertRedirect();
        $program = AssistanceProgram::firstOrFail();
        $this->assertSame('closed', $program->status);
        $this->actingAs($farmer)->get('/farmer/services')->assertOk()->assertDontSee('Private Rice Program');
        $this->actingAs($staff)->post('/modules/aasms/programs/'.$program->id.'/toggle')->assertRedirect();
        $this->actingAs($farmer)->get('/farmer/services')->assertOk()->assertSee('Private Rice Program');
    }
}
