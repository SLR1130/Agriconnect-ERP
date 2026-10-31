<x-layouts.farmer>
    <x-slot name="title">Notifications</x-slot>
    <x-slot name="description">Read status updates sent to your own account by AgriConnect.</x-slot>

    <section class="space-y-3">
        @forelse($notifications as $notification)
            @php
                $type = data_get($notification->data, 'type');
                $destinationId = match ($type) {
                    'production_submission_review' => data_get($notification->data, 'submission_id'),
                    'assistance_application_status', 'assistance_distribution_completed' => data_get($notification->data, 'application_id'),
                    'equipment_request_status' => data_get($notification->data, 'equipment_request_id'),
                    'agricultural_announcement' => data_get($notification->data, 'announcement_id'),
                    default => null,
                };
                $destination = match ($type) {
                    'farmer_registration_status' => route('farmer.farms'),
                    'production_submission_review' => $destinationId ? route('farmer.production.submissions.show', $destinationId) : null,
                    'assistance_application_status', 'assistance_distribution_completed' => $destinationId ? route('farmer.applications.show', $destinationId) : null,
                    'equipment_request_status' => $destinationId ? route('farmer.equipment-requests.show', $destinationId) : null,
                    'agricultural_announcement' => $destinationId ? route('farmer.announcements.show', $destinationId) : null,
                    default => null,
                };
                $destinationLabel = match ($type) {
                    'farmer_registration_status' => 'View registration progress',
                    'production_submission_review' => 'View production submission',
                    'assistance_application_status', 'assistance_distribution_completed' => 'View assistance application',
                    'equipment_request_status' => 'View equipment request',
                    'agricultural_announcement' => 'Read official notice',
                    default => null,
                };
            @endphp

            <article id="notification-{{ $notification->id }}" class="farmer-card {{ !$notification->read_at ? 'border-emerald-400' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm leading-6 text-slate-800">{{ data_get($notification->data, 'message', 'An update is available for your account.') }}</p>
                        @if(data_get($notification->data, 'reason'))
                            <p class="mt-1 text-sm text-slate-600">{{ data_get($notification->data, 'reason') }}</p>
                        @endif
                        @if(data_get($notification->data, 'reference'))
                            <p class="mt-1 text-xs text-slate-500">Reference {{ data_get($notification->data, 'reference') }}</p>
                        @endif
                        <p class="mt-2 text-xs text-slate-500">{{ $notification->created_at?->format('M d, Y h:i A') }}</p>
                        @if($destination)
                            <a class="mt-2 inline-block text-sm font-bold text-emerald-700 underline" href="{{ $destination }}">{{ $destinationLabel }}</a>
                        @endif
                    </div>

                    @if(!$notification->read_at)
                        <form method="POST" action="{{ route('farmer.notifications.read', $notification->id) }}">
                            @csrf
                            <button class="farmer-button farmer-button-light" type="submit">Mark read</button>
                        </form>
                    @else
                        <span class="farmer-pill">Read</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="farmer-empty">No notifications yet. Verification and service status updates will appear here when available.</div>
        @endforelse
    </section>

    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.farmer>
