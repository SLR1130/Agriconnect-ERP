<x-layouts.farmer>
    <section class="farmer-home-hero" aria-labelledby="farmer-home-title">
        <div>
            <p class="farmer-eyebrow"><x-ui.icon name="home" size="sm" />Farmer services</p>
            <h1 id="farmer-home-title">Good day, {{ $farmer->user->name }}.</h1>
            <p>Use one place to keep farm details current, request municipal support, borrow available equipment, and follow every MAO decision.</p>
            <div class="farmer-home-hero-actions">
                <a class="farmer-button farmer-button-gold" href="{{ route('farmer.services') }}"><x-ui.icon name="services" size="sm" />Find a farm service</a>
                <a class="farmer-button farmer-button-light" href="{{ route('farmer.farms') }}"><x-ui.icon name="farms" size="sm" />Manage my farms</a>
            </div>
        </div>
        <aside class="farmer-home-hero-aside" aria-label="Your farmer record">
            <p class="farmer-eyebrow">Your farmer record</p>
            <strong>Registration {{ str($farmer->registration_status)->replace('_', ' ')->lower() }}</strong>
            <span>{{ $farmer->farms->count() }} registered {{ $farmer->farms->count() === 1 ? 'farm' : 'farms' }}. Keep the pin, boundary, and agricultural activities current for MAO review.</span>
        </aside>
    </section>

    @if (!$farmer->isVerified())
        <div class="farmer-message {{ $farmer->registration_status === 'rejected' ? 'farmer-message-error' : 'farmer-message-success' }} mt-5"
            role="status">
            <strong>Registration {{ str($farmer->registration_status)->replace('_', ' ')->lower() }}.</strong>
            @if ($farmer->registration_status === 'pending')
                The MAO is reviewing your registration. You can explore services while you wait; applications and
                equipment requests require verification.
            @elseif($farmer->registration_status === 'returned')
                The MAO needs a correction. Review the note on My Farms and contact the office.
            @else
                Review the MAO feedback on My Farms and contact the office for next steps.
            @endif
            <a class="font-bold underline" href="{{ route('farmer.farms') }}">View registration progress</a>
        </div>
    @endif

    @php
        $nextAction = $needsAttention->isNotEmpty()
            ? ['eyebrow' => 'Recommended next step', 'title' => 'Review a returned service item', 'copy' => 'The MAO sent feedback on one of your submissions. Open My activities to see what needs your attention.', 'label' => 'Open My activities', 'route' => route('farmer.activities'), 'icon' => 'action']
            : (!$farmer->isVerified()
                ? ['eyebrow' => 'Recommended next step', 'title' => 'Complete your farmer registration', 'copy' => 'Keep your farm details and map pin accurate so the MAO can verify your record.', 'label' => 'Open My farms', 'route' => route('farmer.farms'), 'icon' => 'location']
                : ($unreadCount > 0
                    ? ['eyebrow' => 'Recommended next step', 'title' => 'Read your latest updates', 'copy' => 'You have unread service or MAO messages that may contain your next action.', 'label' => 'Open notifications', 'route' => route('farmer.notifications'), 'icon' => 'notifications']
                    : ['eyebrow' => 'Recommended next step', 'title' => 'Explore available agricultural services', 'copy' => 'Browse published assistance programs and choose a service when you are ready to proceed.', 'label' => 'Explore services', 'route' => route('farmer.services'), 'icon' => 'services']));
    @endphp
    <section class="farmer-section" aria-labelledby="farmer-next-action-title">
        <div class="farmer-next-action">
            <span class="farmer-next-action-icon" aria-hidden="true"><x-ui.icon :name="$nextAction['icon']" size="md" /></span>
            <div class="min-w-0">
                <p class="farmer-eyebrow">{{ $nextAction['eyebrow'] }}</p>
                <h2 id="farmer-next-action-title">{{ $nextAction['title'] }}</h2>
                <p>{{ $nextAction['copy'] }}</p>
            </div>
            <a class="farmer-button farmer-button-light" href="{{ $nextAction['route'] }}">{{ $nextAction['label'] }} <x-ui.icon name="next" size="sm" /></a>
        </div>
    </section>

    <section class="farmer-section" aria-labelledby="farmer-services-title">
        <div class="farmer-section-heading">
            <div>
                <p class="farmer-eyebrow">Start a service</p>
                <h2 id="farmer-services-title">What do you need to do today?</h2>
                <p>Choose a clear task. Each page shows only your records and the next action you can take.</p>
            </div>
            <a class="farmer-text-link" href="{{ route('farmer.services') }}">View all services</a>
        </div>
        <div class="farmer-home-service-grid">
            <a class="farmer-home-service farmer-home-service-emphasis" href="{{ route('farmer.equipment') }}"><span aria-hidden="true"><x-ui.icon name="equipment" size="md" /></span><h3>Borrow MAO equipment</h3><p>Choose an available asset, request a schedule, and follow approval, issue, and return.</p><small>Open equipment borrowing <x-ui.icon name="next" size="sm" /></small></a>
            <a class="farmer-home-service" href="{{ route('farmer.assistance') }}"><span aria-hidden="true"><x-ui.icon name="assistance" size="md" /></span><h3>Apply for assistance</h3><p>Review published programs and submit an application when you qualify.</p><small>Open assistance <x-ui.icon name="next" size="sm" /></small></a>
            <a class="farmer-home-service" href="{{ route('farmer.production') }}"><span aria-hidden="true"><x-ui.icon name="crop" size="md" /></span><h3>Update farm production</h3><p>Send crop, livestock, or fishery information for MAO review.</p><small>Open production records <x-ui.icon name="next" size="sm" /></small></a>
        </div>
    </section>

    <section class="farmer-section" aria-labelledby="featured-programs-title">
        <div class="farmer-section-heading">
            <div>
                <h2 id="featured-programs-title" class="flex items-center gap-2">
                    <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Programs accepting applications
                </h2>
                <p class="text-slate-600">Published assistance from the Municipal Agriculture Office.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a class="text-sm font-bold text-emerald-700 underline transition-colors hover:text-emerald-900"
                    href="{{ route('public.agricultural-services.index') }}">Public program directory</a>
                <a class="text-sm font-bold text-emerald-700 underline transition-colors hover:text-emerald-900"
                    href="{{ route('farmer.services') }}">My services</a>
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            @forelse($programs as $program)
                @php($programNotice = $program->announcements->first())
                @php($programCover = $programNotice?->publishedRevision?->media?->first(fn($media) => $media->is_cover) ?? $programNotice?->publishedRevision?->media?->first())
                <article
                    class="farmer-card group flex flex-col overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:border-emerald-400 hover:shadow-2xl">
                    @if ($programCover)
                        <div class="relative overflow-hidden">
                            <img class="farmer-feature-image transition-transform duration-300 group-hover:scale-105"
                                src="{{ route('public.announcements.media', [$programNotice, $programCover]) }}"
                                alt="{{ $programCover->alt_text }}" loading="lazy">
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                            </div>
                        </div>
                    @endif
                    <div class="relative">
                        <span class="farmer-pill self-start animate-pulse">
                            <svg class="mr-1 inline-block h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                <circle cx="10" cy="10" r="3" />
                            </svg>
                            Open for applications
                        </span>
                        <h3 class="mt-3 text-lg font-bold transition-colors group-hover:text-emerald-700">
                            {{ $program->name }}</h3>
                        <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">
                            {{ $program->description ?: 'See the program requirements and application details.' }}
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                            <span class="flex items-center gap-1">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ $program->closes_on ? 'Apply by ' . $program->closes_on->format('M d, Y') : 'No closing date' }}
                            </span>
                            <span class="text-slate-400">·</span>
                            <span class="flex items-center gap-1">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ data_get($program->eligibility_rules, 'requires_farm') ? 'Farm required' : 'Verification required' }}
                            </span>
                        </div>
                        <a class="farmer-button farmer-button-light mt-4 self-start transition-all hover:scale-105 hover:bg-emerald-50 hover:text-emerald-700"
                            href="{{ route('farmer.programs.show', $program) }}">View details</a>
                    </div>
                </article>
            @empty
                <div class="farmer-empty md:col-span-3">No assistance programs are accepting applications right now.
                    You
                    can still review your farm information and check back for MAO updates.</div>
            @endforelse
        </div>
    </section>

    @if ($needsAttention->isNotEmpty())
        <section class="farmer-section farmer-attention-section" aria-labelledby="farmer-attention-title">
            <div class="farmer-section-heading">
                <div>
                    <h2 id="farmer-attention-title">Needs your attention</h2>
                    <p>These services were returned by the MAO and may need a correction.</p>
                </div>
            </div>
            <ol class="farmer-activity-list">
                @foreach ($needsAttention as $activity)
                    <li><x-farmer.activity-item :activity="$activity" compact /></li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="farmer-section grid gap-5 lg:grid-cols-[1.3fr_.7fr]"
        aria-label="Your recent activity and updates">
        <div class="farmer-card">
            <div class="farmer-section-heading">
                <div>
                    <h2>Recent service activity</h2>
                    <p>Your latest records and status updates, across agricultural services.</p>
                </div><a class="farmer-text-link" href="{{ route('farmer.activities') }}">View all activities</a>
            </div>
            @if ($recentActivities->isNotEmpty())
                <ol class="farmer-activity-list farmer-home-activity-list">
                    @foreach ($recentActivities as $activity)
                        <li><x-farmer.activity-item :activity="$activity" compact /></li>
                    @endforeach
                </ol>
            @else
                <div class="farmer-empty">Your service records and submitted information will appear here as you use
                    agricultural services.</div>
            @endif
        </div>
        <div class="space-y-4">
            <div class="farmer-card">
                <p class="farmer-eyebrow">Your registration</p>
                <h2 class="mt-2 text-xl font-bold">{{ str($farmer->registration_status)->replace('_', ' ')->headline() }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ $farmer->farmer_code }} · {{ $farmer->farms->count() }} registered {{ $farmer->farms->count() === 1 ? 'farm' : 'farms' }}</p><a
                    class="mt-4 inline-block text-sm font-bold text-emerald-700 underline"
                    href="{{ route('farmer.farms') }}">View registration details</a>
            </div>
            <div class="farmer-card">
                <p class="farmer-eyebrow">Notifications</p>
                <h2 class="mt-2 text-xl font-bold">{{ $unreadCount }} unread
                    message{{ $unreadCount === 1 ? '' : 's' }}</h2>
                <p class="mt-2 text-sm text-slate-600">Read announcements and direct service updates in your inbox.</p>
                <a class="mt-4 inline-block text-sm font-bold text-emerald-700 underline"
                    href="{{ route('farmer.notifications') }}">Open notifications</a>
            </div>
        </div>
    </section>

    <section class="farmer-section grid gap-4 md:grid-cols-2" aria-label="Distributions and official notices">
        <div class="farmer-card">
            <div class="farmer-section-heading">
                <div>
                    <h2>Assistance received</h2>
                    <p>Your completed supply distributions.</p>
                </div>
            </div>
            @forelse($distributions as $distribution)
                <p class="border-t border-slate-100 py-3 text-sm"><strong
                        class="text-forest-green">{{ $distribution->application->program->name }}</strong><br>{{ $distribution->batch->item->name }}
                · {{ $distribution->quantity }} · {{ $distribution->distributed_at?->format('M d, Y') }}</p>@empty
                <p class="text-sm text-slate-600">No supplies have been distributed to you yet.</p>
            @endforelse
        </div>
        @if ($announcementsAvailable)
            <div class="farmer-card">
                <div class="farmer-section-heading">
                    <div>
                        <h2>MAO announcements</h2>
                        <p>Official agricultural notices.</p>
                    </div><a class="text-sm font-bold text-emerald-700 underline"
                        href="{{ route('public.agricultural-services.index') }}">Browse all</a>
                </div>
                @forelse($announcements as $announcement)
                    @php($publication = $announcementPresentations[$announcement->id])@php($cover = $publication['media']->first(fn($media) => $media->is_cover) ?? $publication['media']->first())
                    <a class="farmer-home-notice" href="{{ route('farmer.announcements.show', $announcement) }}">
                        @if ($cover)
                            <img src="{{ route('public.announcements.media', [$announcement, $cover]) }}"
                                alt="{{ $cover->alt_text }}" loading="lazy">
                        @endif
                        <span>
                            <strong>{{ $publication['content']['title'] }}</strong><small>{{ $announcement->published_at->timezone('Asia/Manila')->format('M d, Y') }}
                                · {{ $publication['content']['office'] ?: 'Bongabong MAO' }}</small></span>
                </a>@empty<p class="text-sm text-slate-600">No official MAO notices are currently published.</p>
                @endforelse
            </div>
        @endif
    </section>
</x-layouts.farmer>
