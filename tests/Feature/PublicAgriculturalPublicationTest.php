<?php

namespace Tests\Feature;

use App\Models\AgriculturalAnnouncement;
use App\Models\AssistanceProgram;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicAgriculturalPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $roleSlug, array $permissions = [], string $registrationStatus = 'pending'): User
    {
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], [
            'name' => str($roleSlug)->headline()->toString(),
            'permissions' => $permissions,
            'is_active' => true,
            'is_default' => in_array($roleSlug, ['super_admin', 'mao_admin', 'municipal_agriculturist', 'module_personnel', 'authorized_viewer', 'farmer'], true),
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        if ($roleSlug === 'farmer') {
            Farmer::query()->create([
                'user_id' => $user->id,
                'farmer_code' => 'PUB-'.$user->id,
                'registration_status' => $registrationStatus,
            ]);
        }

        return $user;
    }

    private function publishedProgram(array $overrides = []): AssistanceProgram
    {
        return AssistanceProgram::query()->create($overrides + [
            'name' => 'Rice Seed Support',
            'category' => 'Crop support',
            'description' => 'Official seed assistance for qualified farmers.',
            'status' => 'open',
            'opens_on' => now()->toDateString(),
            'closes_on' => now()->addMonth()->toDateString(),
            'eligibility_rules' => ['requires_verified_farmer' => true],
        ]);
    }

    private function draftPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Official Seed Distribution Notice',
            'body' => "## Service information\n\nThe MAO will assist **local farmers**. <script>alert('xss')</script> [unsafe](javascript:alert(1))",
            'office' => 'Bongabong Municipal Agriculture Office',
            'category' => 'Crop support',
            'service_area' => 'Bongabong, Oriental Mindoro',
            'requirements_summary' => 'Verified farmer registration.',
            'instructions' => 'Review the requirements before applying.',
            'contact_details' => 'Visit the Municipal Agriculture Office.',
            'expires_at' => '',
            'assistance_program_id' => '',
        ];
    }

    public function test_public_users_can_browse_only_published_records_and_private_images_require_staff_access(): void
    {
        Storage::fake('local');
        $editor = $this->account('module_personnel', [
            'modules.aasms.view', 'modules.aasms.create', 'modules.aasms.update', 'modules.aasms.*',
        ]);
        $draftResponse = $this->actingAs($editor)->post(route('modules.aasms.announcements.store'), $this->draftPayload([
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 1200, 700),
            'cover_alt' => 'Farmers at a seed distribution site',
        ]));
        $draftResponse->assertRedirect()->assertSessionHas('status');

        $announcement = AgriculturalAnnouncement::query()->with('currentRevision.media')->firstOrFail();
        $revision = $announcement->currentRevision;
        $media = $revision->media->firstOrFail();
        $this->assertSame('draft', $announcement->status);
        $this->assertSame('draft', $revision->state);
        Storage::disk('local')->assertExists($media->storage_path);

        $this->actingAs($editor)->get(route('modules.aasms.announcements'))
            ->assertOk()->assertSee('Awaiting a user with explicit publication permission.')
            ->assertDontSee('Publish revision');
        $this->actingAs($editor)->post(route('modules.aasms.announcements.publish', $announcement))->assertForbidden();
        $this->get(route('public.agricultural-services.index'))->assertOk()
            ->assertDontSee('Official Seed Distribution Notice');
        $this->get(route('public.announcements.show', $announcement))->assertNotFound();
        $this->get(route('public.announcements.media', [$announcement, $media]))->assertNotFound();

        $this->actingAs($editor)->get(route('modules.aasms.announcements.preview', [$announcement, $revision]))
            ->assertOk()->assertSee('Private preview')->assertSee('Official Seed Distribution Notice');
        $staffImage = $this->actingAs($editor)->get(route('modules.aasms.announcements.media', [$announcement, $revision, $media]))
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $staffCacheControl = (string) $staffImage->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $staffCacheControl);
        $this->assertStringContainsString('no-store', $staffCacheControl);
        $this->assertStringNotContainsString('public', $staffCacheControl);
    }

    public function test_explicit_mao_publish_grant_publishes_safe_media_rich_content_and_notifies_farmers(): void
    {
        Storage::fake('local');
        $publisher = $this->account('mao_admin', [
            'modules.aasms.view', 'modules.aasms.create', 'modules.aasms.update', 'modules.aasms.publish',
        ]);
        $farmer = $this->account('farmer');
        $program = $this->publishedProgram();

        $this->actingAs($publisher)->post(route('modules.aasms.announcements.store'), $this->draftPayload([
            'assistance_program_id' => $program->id,
            'cover_image' => UploadedFile::fake()->image('cover.png', 1200, 700),
            'cover_alt' => 'Farmers receiving seed assistance',
            'gallery_images' => [UploadedFile::fake()->image('field.jpg', 900, 600)],
            'gallery_alt' => ['Rice field prepared for planting'],
        ]))->assertRedirect();

        $announcement = AgriculturalAnnouncement::query()->with('currentRevision.media')->firstOrFail();
        $revision = $announcement->currentRevision;
        $this->assertCount(2, $revision->media);
        $cover = $revision->media->firstWhere('is_cover', true);
        $this->assertNotNull($cover);
        $this->assertSame('Farmers receiving seed assistance', $cover->alt_text);
        $this->assertDatabaseHas('agricultural_announcement_revisions', [
            'id' => $revision->id,
            'announcement_id' => $announcement->id,
            'state' => 'draft',
        ]);

        $this->actingAs($publisher)->post(route('modules.aasms.announcements.publish', $announcement))->assertRedirect();
        $announcement->refresh();
        $this->assertSame('published', $announcement->status);
        $this->assertSame($revision->id, $announcement->published_revision_id);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $farmer->id]);

        $this->get(route('public.agricultural-services.index'))->assertOk()
            ->assertSee('Official Seed Distribution Notice')->assertSee('Accepting applications');
        $detail = $this->get(route('public.announcements.show', $announcement))->assertOk()
            ->assertSee('<strong>local farmers</strong>', false)
            ->assertDontSee('<script>', false)
            ->assertDontSee('href="javascript:alert(1)"', false)
            ->assertSee('Bongabong Municipal Agriculture Office')
            ->assertSee(route('public.agricultural-programs.show', $program), false);
        $this->assertStringNotContainsString(Storage::disk('local')->path($cover->storage_path), $detail->getContent());
        $publicImage = $this->get(route('public.announcements.media', [$announcement, $cover]))
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $publicImage->headers->get('Cache-Control'));
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_publication_form_preserves_filipino_unicode_and_renders_it_in_the_public_feed_and_detail(): void
    {
        $publisher = $this->account('mao_admin', [
            'modules.aasms.view', 'modules.aasms.create', 'modules.aasms.update', 'modules.aasms.publish',
        ]);
        $programName = "SYNTHETIC QA ONLY \u{2014} Tulong sa binhi ng palay";
        $title = "Abiso para sa mga magsasaka \u{2014} panahon ng taniman";
        $body = "Magtanim nang tama; tulong na \u{20B1}5,000 para sa \u{201C}palay\u{201D} at binhi.";
        $program = $this->publishedProgram(['name' => $programName]);

        $this->actingAs($publisher)->post(route('modules.aasms.announcements.store'), $this->draftPayload([
            'title' => $title,
            'body' => $body,
            'category' => 'Service advisory',
            'assistance_program_id' => $program->id,
        ]))->assertRedirect();

        $announcement = AgriculturalAnnouncement::query()->with('currentRevision')->firstOrFail();
        $this->assertSame($title, $announcement->currentRevision->content['title']);
        $this->assertSame($body, $announcement->currentRevision->content['body']);
        $this->assertDatabaseCount('agricultural_announcements', 1);
        $this->assertDatabaseCount('assistance_programs', 1);

        $this->actingAs($publisher)->post(route('modules.aasms.announcements.publish', $announcement))->assertRedirect();

        $this->get(route('public.agricultural-services.index'))
            ->assertOk()
            ->assertSee($title)
            ->assertSee('Service advisory')
            ->assertSee($programName)
            ->assertDontSee("\u{00C3}")
            ->assertDontSee("\u{00E2}\u{20AC}");

        $this->get(route('public.announcements.show', $announcement))
            ->assertOk()
            ->assertSee($title)
            ->assertSee('Magtanim nang tama;')
            ->assertSee('Accepting applications')
            ->assertSee('Sign in to check eligibility')
            ->assertSee(route('public.agricultural-programs.show', $program), false);
    }

    public function test_published_edits_are_private_revisions_until_approved_and_archive_preserves_history(): void
    {
        Storage::fake('local');
        $publisher = $this->account('mao_admin', [
            'modules.aasms.view', 'modules.aasms.create', 'modules.aasms.update', 'modules.aasms.publish',
        ]);
        $this->actingAs($publisher)->post(route('modules.aasms.announcements.store'), $this->draftPayload([
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 1000, 600),
            'cover_alt' => 'Municipal agricultural service site',
            'gallery_images' => [UploadedFile::fake()->image('gallery.jpg', 900, 600)],
            'gallery_alt' => ['Rice planting area prepared for the season'],
        ]))->assertRedirect();
        $announcement = AgriculturalAnnouncement::query()->with('currentRevision.media')->firstOrFail();
        $firstRevision = $announcement->currentRevision;
        $oldMedia = $firstRevision->media->firstOrFail();
        $oldGallery = $firstRevision->media->firstWhere('is_cover', false);
        $this->actingAs($publisher)->post(route('modules.aasms.announcements.publish', $announcement))->assertRedirect();
        $this->get(route('public.announcements.show', $announcement))->assertOk()->assertSee('Official Seed Distribution Notice');

        $this->actingAs($publisher)->patch(route('modules.aasms.announcements.update', $announcement), $this->draftPayload([
            'title' => 'Updated Seed Distribution Schedule',
            'cover_alt' => '',
            'keep_media_ids' => [$oldMedia->id, $oldGallery->id],
            'media_order' => [$oldGallery->id, $oldMedia->id],
            'media_alt' => [$oldMedia->id => 'Updated description of the municipal service location', $oldGallery->id => 'Rice planting area prepared for the season'],
            'existing_cover_id' => $oldMedia->id,
        ]))->assertRedirect();

        $announcement->refresh()->load('currentRevision.media');
        $pendingRevision = $announcement->currentRevision;
        $this->assertSame('published', $announcement->status);
        $this->assertSame($firstRevision->id, $announcement->published_revision_id);
        $this->assertSame('draft', $pendingRevision->state);
        $this->assertCount(2, $pendingRevision->media);
        $pendingImages = $pendingRevision->media;
        $this->assertSame($oldGallery->storage_path, $pendingImages->first()->storage_path);
        $this->assertFalse($pendingImages->first()->is_cover);
        $this->assertSame($oldMedia->storage_path, $pendingImages->last()->storage_path);
        $this->assertTrue($pendingImages->last()->is_cover);
        $this->get(route('public.announcements.show', $announcement))->assertOk()->assertSee('Official Seed Distribution Notice')->assertDontSee('Updated Seed Distribution Schedule');
        $this->actingAs($publisher)->get(route('modules.aasms.announcements.preview', [$announcement, $pendingRevision]))
            ->assertOk()->assertSee('Updated Seed Distribution Schedule');
        $newMedia = $pendingRevision->media->firstOrFail();
        $this->get(route('public.announcements.media', [$announcement, $newMedia]))->assertNotFound();

        $this->actingAs($publisher)->post(route('modules.aasms.announcements.publish', $announcement))->assertRedirect();
        $announcement->refresh()->load('revisions');
        $this->assertSame('published', $announcement->status);
        $this->assertSame('superseded', $announcement->revisions->firstWhere('id', $firstRevision->id)->state);
        $this->get(route('public.announcements.show', $announcement))->assertOk()
            ->assertSee('Updated Seed Distribution Schedule')->assertDontSee('Official Seed Distribution Notice');
        $this->get(route('public.announcements.media', [$announcement, $newMedia]))->assertOk();

        $publishedMedia = $announcement->publishedRevision->media()->get();
        $revisedCover = $publishedMedia->firstWhere('is_cover', true);
        $this->actingAs($publisher)->patch(route('modules.aasms.announcements.update', $announcement), $this->draftPayload([
            'title' => 'Final Seed Distribution Schedule',
            'keep_media_ids' => [$revisedCover->id],
            'media_order' => [$revisedCover->id],
            'media_alt' => [$revisedCover->id => $revisedCover->alt_text],
            'existing_cover_id' => $revisedCover->id,
        ]))->assertRedirect();
        $announcement->refresh()->load('currentRevision.media');
        $removedGalleryRevision = $announcement->currentRevision;
        $this->assertCount(1, $removedGalleryRevision->media);
        $this->assertCount(2, $announcement->publishedRevision->media()->get());
        $this->actingAs($publisher)->post(route('modules.aasms.announcements.publish', $announcement))->assertRedirect();
        $this->get(route('public.announcements.show', $announcement))->assertOk()->assertSee('Final Seed Distribution Schedule');

        $revisionCount = $announcement->revisions()->count();
        $this->actingAs($publisher)->post(route('modules.aasms.announcements.archive', $announcement))->assertRedirect();
        $announcement->refresh();
        $this->assertSame('archived', $announcement->status);
        $this->assertSame($revisionCount + 1, $announcement->revisions()->count());
        $this->get(route('public.announcements.show', $announcement))->assertNotFound();
        $this->get(route('public.announcements.media', [$announcement, $newMedia]))->assertNotFound();
    }

    public function test_publishing_is_default_deny_and_program_publication_uses_the_same_explicit_permission(): void
    {
        $legacyAdmin = $this->account('mao_admin', ['modules.aasms.*']);
        $modulePersonnel = $this->account('module_personnel', ['modules.aasms.*']);
        $program = AssistanceProgram::query()->create([
            'name' => 'Private Program Details', 'description' => 'Unpublished information must remain hidden.',
            'status' => 'closed', 'eligibility_rules' => [],
        ]);
        $this->actingAs($legacyAdmin)->post(route('modules.aasms.announcements.store'), $this->draftPayload([
            'title' => 'Private linked announcement',
            'body' => 'Private linked details.',
            'assistance_program_id' => $program->id,
        ]))->assertRedirect();
        $draft = AgriculturalAnnouncement::query()->firstOrFail();
        $this->actingAs($legacyAdmin)->post(route('modules.aasms.programs.toggle', $program))->assertForbidden();
        $this->actingAs($legacyAdmin)->post(route('modules.aasms.announcements.publish', $draft))->assertForbidden();
        $this->actingAs($modulePersonnel)->post(route('modules.aasms.programs.toggle', $program))->assertForbidden();
        $this->assertFalse($legacyAdmin->canModuleAction('aasms', 'publish'));
        $this->assertFalse($modulePersonnel->canModuleAction('aasms', 'publish'));

        $legacyAdmin->roles()->firstOrFail()->update(['permissions' => ['modules.aasms.view', 'modules.aasms.publish']]);
        $this->actingAs($legacyAdmin)->post(route('modules.aasms.announcements.publish', $draft))
            ->assertSessionHasErrors('assistance_program_id');
        $this->get(route('public.agricultural-services.index'))->assertOk()
            ->assertDontSee('Private linked announcement')->assertDontSee('Private Program Details');
        $this->get(route('public.announcements.show', $draft))->assertNotFound();
        $this->get(route('public.agricultural-programs.show', $program))->assertNotFound();

        $superAdmin = $this->account('super_admin', ['*']);
        $this->assertTrue($superAdmin->canModuleAction('aasms', 'publish'));
    }

    public function test_public_program_filters_use_published_visibility_and_inclusive_manila_dates(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Manila')->startOfDay()->setTime(12, 0));
        $today = now('Asia/Manila')->toDateString();
        $this->publishedProgram(['name' => 'Open Today', 'opens_on' => $today, 'closes_on' => $today]);
        $this->publishedProgram(['name' => 'Opens Tomorrow', 'opens_on' => now('Asia/Manila')->addDay()->toDateString(), 'closes_on' => null]);
        $endedProgram = $this->publishedProgram(['name' => 'Ended Yesterday', 'opens_on' => null, 'closes_on' => now('Asia/Manila')->subDay()->toDateString()]);
        AssistanceProgram::query()->create(['name' => 'Private Program', 'description' => 'Must not show.', 'status' => 'closed', 'eligibility_rules' => []]);

        $this->get(route('public.agricultural-services.index', ['availability' => 'available']))
            ->assertOk()->assertSee('Open Today')->assertSee('Sign in to check eligibility')->assertDontSee('Opens Tomorrow')->assertDontSee('Ended Yesterday')->assertDontSee('Private Program');
        $this->get(route('public.agricultural-services.index', ['availability' => 'upcoming']))
            ->assertOk()->assertSee('Opens Tomorrow')->assertDontSee('Private Program');
        $this->get(route('public.agricultural-services.index', ['availability' => 'ended']))
            ->assertOk()->assertSee('Ended Yesterday')->assertSee('role="img" aria-label="No official photograph provided for this assistance program"', false)
            ->assertDontSee('Sign in to check eligibility')->assertDontSee(route('farmer.programs.show', $endedProgram), false)->assertDontSee('Private Program');
        $this->get(route('public.agricultural-services.index', ['availability' => 'all']))
            ->assertOk()->assertSee('Open Today')->assertSee('Opens Tomorrow')->assertSee('Ended Yesterday')->assertDontSee('Private Program');
        $this->get(route('public.agricultural-programs.show', $endedProgram))->assertOk()
            ->assertSee('The application period has ended.')
            ->assertDontSee(route('farmer.programs.show', $endedProgram), false)
            ->assertDontSee('Submit application')
            ->assertDontSee(route('farmer.assistance.apply', $endedProgram), false);
        $verifiedFarmer = $this->account('farmer', [], 'verified');
        $this->actingAs($verifiedFarmer)->post(route('farmer.assistance.apply', $endedProgram), [])
            ->assertSessionHasErrors('program');
        $this->assertDatabaseCount('assistance_applications', 0);
    }

    public function test_public_announcement_details_never_offer_application_actions_for_upcoming_or_ended_programs(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Manila')->startOfDay()->setTime(12, 0));
        $publisher = $this->account('mao_admin', [
            'modules.aasms.view', 'modules.aasms.create', 'modules.aasms.update', 'modules.aasms.publish',
        ]);
        $upcomingProgram = $this->publishedProgram([
            'name' => 'Upcoming public assistance',
            'opens_on' => now('Asia/Manila')->addDay()->toDateString(),
            'closes_on' => now('Asia/Manila')->addMonth()->toDateString(),
        ]);
        $endedProgram = $this->publishedProgram([
            'name' => 'Ended public assistance',
            'opens_on' => null,
            'closes_on' => now('Asia/Manila')->subDay()->toDateString(),
        ]);

        foreach ([
            [$upcomingProgram, 'Upcoming assistance announcement'],
            [$endedProgram, 'Ended assistance announcement'],
        ] as [$program, $title]) {
            $this->actingAs($publisher)->post(route('modules.aasms.announcements.store'), $this->draftPayload([
                'title' => $title,
                'body' => 'Synthetic publication used to verify application availability messaging.',
                'assistance_program_id' => $program->id,
            ]))->assertRedirect();

            $announcement = AgriculturalAnnouncement::query()->where('assistance_program_id', $program->id)->firstOrFail();
            $this->actingAs($publisher)->post(route('modules.aasms.announcements.publish', $announcement))->assertRedirect();

            $detail = $this->get(route('public.announcements.show', $announcement))->assertOk();
            $detail->assertDontSee(route('farmer.programs.show', $program), false)
                ->assertDontSee('Check eligibility and apply')
                ->assertDontSee('Submit application');

            if ($program->opens_on?->isAfter(now('Asia/Manila')->toDateString())) {
                $detail->assertSee('Applications are not open yet.');
            } else {
                $detail->assertSee('The application period has ended.');
            }
        }
    }

    public function test_program_publication_is_audited_and_requires_the_separate_publish_permission(): void
    {
        $editor = $this->account('mao_admin', ['modules.aasms.view', 'modules.aasms.update']);
        $program = AssistanceProgram::query()->create([
            'name' => 'Private Soil Testing Service',
            'description' => 'Program should remain private until publication.',
            'status' => 'closed',
            'eligibility_rules' => [],
        ]);

        $this->actingAs($editor)->post(route('modules.aasms.programs.toggle', $program))->assertForbidden();
        $this->get(route('public.agricultural-services.index'))->assertOk()->assertDontSee('Private Soil Testing Service');

        $role = $editor->roles()->firstOrFail();
        $role->update(['permissions' => ['modules.aasms.view', 'modules.aasms.update', 'modules.aasms.publish']]);
        $this->actingAs($editor)->post(route('modules.aasms.programs.toggle', $program))->assertRedirect();
        $this->assertDatabaseHas('assistance_programs', ['id' => $program->id, 'status' => 'open']);
        $this->assertDatabaseHas('activity_logs', [
            'action_type' => 'published',
            'action_target' => 'assistance_program:'.$program->id,
            'module' => 'aasms',
        ]);
        $this->get(route('public.agricultural-services.index'))->assertOk()->assertSee('Private Soil Testing Service');
    }

    public function test_farmer_program_detail_and_application_continue_through_existing_eligibility_workflow(): void
    {
        $program = $this->publishedProgram();
        $verifiedFarmer = $this->account('farmer', [], 'verified');

        $this->actingAs($verifiedFarmer)->get(route('public.agricultural-services.index'))
            ->assertOk()->assertSee(route('farmer.programs.show', $program), false)->assertSee('Check eligibility');
        $this->actingAs($verifiedFarmer)->get(route('public.agricultural-programs.show', $program))
            ->assertOk()->assertSee(route('farmer.programs.show', $program), false);
        $this->actingAs($verifiedFarmer)->get(route('farmer.programs.show', $program))
            ->assertOk()->assertSee(route('farmer.assistance.apply', $program), false)
            ->assertSee('Submit application');
        $this->actingAs($verifiedFarmer)->post(route('farmer.assistance.apply', $program), ['purpose' => 'Support our registered farm.'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('assistance_applications', [
            'program_id' => $program->id,
            'farmer_id' => $verifiedFarmer->farmer->id,
            'status' => 'submitted',
        ]);

        $pendingFarmer = $this->account('farmer', [], 'pending');
        $this->actingAs($pendingFarmer)->post(route('farmer.assistance.apply', $program), [])
            ->assertForbidden();
        $this->assertDatabaseCount('assistance_applications', 1);
    }

    public function test_image_upload_requires_valid_safe_format_and_descriptive_alt_text(): void
    {
        Storage::fake('local');
        $editor = $this->account('module_personnel', ['modules.aasms.view', 'modules.aasms.create']);
        $this->actingAs($editor)->post(route('modules.aasms.announcements.store'), $this->draftPayload([
            'cover_image' => UploadedFile::fake()->create('not-an-image.svg', 10, 'image/svg+xml'),
            'cover_alt' => '',
        ]))->assertSessionHasErrors(['cover_image', 'cover_alt']);
        $this->assertDatabaseCount('agricultural_announcements', 0);

        $this->actingAs($editor)->post(route('modules.aasms.announcements.store'), $this->draftPayload([
            'gallery_images' => [UploadedFile::fake()->image('gallery.png', 800, 500)],
            'gallery_alt' => [''],
        ]))->assertSessionHasErrors('gallery_alt.0');
        $this->assertDatabaseCount('agricultural_announcements', 0);
    }
}
