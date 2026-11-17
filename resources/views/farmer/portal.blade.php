<x-layouts.farmer>
    <section class="farmer-hero relative overflow-hidden">
        <!-- Enhanced Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-10 right-10 h-32 w-32 rounded-full bg-white/20 blur-3xl"></div>
            <div class="absolute bottom-10 left-10 h-40 w-40 rounded-full bg-white/10 blur-3xl"></div>
        </div>

        <!-- Content -->
        <div class="relative z-10">
            <p
                class="mb-3 inline-block rounded-full bg-yellow-400/20 px-3 py-1.5 text-xs font-bold uppercase tracking-[.17em] text-yellow-200 backdrop-blur-sm">
                <svg class="mr-1.5 inline-block h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                    <path
                        d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                </svg>
                Your agriculture, connected
            </p>

            <h1 class="bg-gradient-to-br from-white to-emerald-100 bg-clip-text text-transparent">
                Good day, {{ $farmer->user->name }}.
            </h1>

            <p class="mt-4 text-emerald-50/90">
                Find local support, keep your farm information current, and follow your requests in one place.
            </p>

            <div class="relative mt-6 flex flex-wrap gap-3">
                <a class="farmer-button farmer-button-gold group relative overflow-hidden transition-all hover:scale-105 hover:shadow-xl"
                    href="{{ route('farmer.services') }}">
                    <span class="relative z-10 flex items-center gap-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                        Explore programs and services
                    </span>
                </a>
                <a class="farmer-button farmer-button-light group relative overflow-hidden backdrop-blur-sm transition-all hover:scale-105 hover:bg-white/30"
                    href="{{ route('farmer.farms') }}">
                    <span class="relative z-10 flex items-center gap-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Manage my farms
                    </span>
                </a>
            </div>
        </div>
    </section>

    @if (!$farmer->isVerified())
        <div class="farmer-message {{ $farmer->registration_status === 'rejected' ? 'farmer-message-error' : 'farmer-message-success' }} mt-5"
            role="status">
            <strong>Registration {{ str_replace('_', ' ', $farmer->registration_status) }}.</strong>
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

    <section class="farmer-section" aria-labelledby="farmer-services-title">
        <div class="farmer-section-heading">
            <div>
                <h2 id="farmer-services-title" class="flex items-center gap-2">
                    <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    What can we help you with?
                </h2>
                <p class="text-slate-600">Choose a service to see requirements and your next step.</p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
        ['Assistance & subsidies', 'Discover open support programs and apply when eligible.', route('farmer.assistance'), 'assistance', 'from-emerald-500 to-teal-600'],
        ['Equipment services', 'See available equipment and request a schedule.', route('farmer.equipment'), 'equipment', 'from-blue-500 to-cyan-600'],
        ['My farms', 'View your registration and update farm records.', route('farmer.farms'), 'farms', 'from-green-500 to-emerald-600'],
        ['Crop & livestock records', 'Send information for MAO review and view official records.', route('farmer.production'), 'crop', 'from-amber-500 to-orange-600'],
        ['Fisheries', 'Send fishery information and review official records.', route('farmer.production', ['section' => 'fisheries']), 'fish', 'from-cyan-500 to-blue-600'],
        ['Farm monitoring', 'Read MAO visits and report pests or disease.', route('farmer.production', ['section' => 'monitoring']), 'monitoring', 'from-purple-500 to-indigo-600'],
        ['Market opportunities', 'See current MAO interest notices and offer available products for review.', route('farmer.market'), 'inventory', 'from-rose-500 to-pink-600'],
    ] as [$name, $copy, $url, $icon, $gradient])
                <a href="{{ $url }}"
                    class="farmer-card group relative block overflow-hidden no-underline transition-all duration-300 hover:-translate-y-1 hover:border-emerald-400 hover:shadow-2xl">
                    <div
                        class="absolute inset-0 bg-gradient-to-br {{ $gradient }} opacity-0 transition-opacity duration-300 group-hover:opacity-5">
                    </div>

                    <div class="relative">
                        <span
                            class="mb-4 inline-grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br {{ $gradient }} text-white shadow-md transition-transform duration-300 group-hover:scale-110 group-hover:shadow-lg"
                            aria-hidden="true">
                            <x-ui.icon :name="$icon" size="md" />
                        </span>
                        <h3 class="text-base font-bold text-slate-800 transition-colors group-hover:text-emerald-700">
                            {{ $name }}</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $copy }}</p>
                        <span
                            class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-emerald-700 transition-all group-hover:gap-2">
                            Open service
                            <x-ui.icon name="next" size="sm" />
                        </span>
                    </div>
                </a>
            @endforeach
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
                <h2 class="mt-2 text-xl font-bold">{{ ucfirst($farmer->registration_status) }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ $farmer->farmer_code }} · {{ $farmer->farms->count() }}
                    registered {{ $farmer->farms->count() === 1 ? 'farm' : 'farms' }}</p><a
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
