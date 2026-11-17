@props(['notification'])
@php
    $type = data_get($notification->data, 'type');
    $id = match ($type) {
        'production_submission_review' => data_get($notification->data, 'submission_id'),
        'assistance_application_status', 'assistance_distribution_completed' => data_get($notification->data, 'application_id'),
        'equipment_request_status' => data_get($notification->data, 'equipment_request_id'),
        'agricultural_announcement' => data_get($notification->data, 'announcement_id'),
        default => null,
    };
    $destinationId = is_int($id) || (is_string($id) && ctype_digit($id) && (int) $id > 0) ? $id : null;
    [$category, $icon] = match ($type) {
        'production_submission_review' => ['Production', 'crop'],
        'assistance_application_status', 'assistance_distribution_completed' => ['Assistance', 'assistance'],
        'equipment_request_status' => ['Equipment', 'equipment'],
        'farmer_registration_status' => ['Registration', 'farms'],
        'agricultural_announcement' => ['Official announcement', 'notifications'],
        default => ['Service update', 'activity'],
    };
    $destination = match ($type) {
        'farmer_registration_status' => route('farmer.farms'),
        'production_submission_review' => $destinationId ? route('farmer.production.submissions.show', $destinationId) : route('farmer.activities'),
        'assistance_application_status', 'assistance_distribution_completed' => $destinationId ? route('farmer.applications.show', $destinationId) : route('farmer.activities'),
        'equipment_request_status' => $destinationId ? route('farmer.equipment-requests.show', $destinationId) : route('farmer.activities'),
        'agricultural_announcement' => $destinationId ? route('farmer.announcements.show', $destinationId) : route('farmer.activities'),
        default => route('farmer.activities'),
    };
    $destinationLabel = match ($type) {
        'farmer_registration_status' => 'View registration progress',
        'production_submission_review' => $destinationId ? 'View production submission' : 'View my activities',
        'assistance_application_status', 'assistance_distribution_completed' => $destinationId ? 'View assistance application' : 'View my activities',
        'equipment_request_status' => $destinationId ? 'View equipment request' : 'View my activities',
        'agricultural_announcement' => $destinationId ? 'Read official notice' : 'View my activities',
        default => 'View my activities',
    };
@endphp

<article id="notification-{{ $notification->id }}" class="farmer-notification-item {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
    <span class="farmer-notification-icon" aria-hidden="true"><x-ui.icon :name="$icon" size="md" /></span>
    <div class="farmer-notification-body">
        <div class="farmer-notification-meta">
            <span class="farmer-notification-category">{{ $category }}</span>
            @if(!$notification->read_at)<span class="farmer-unread-indicator"><span aria-hidden="true"></span>Unread</span>@else<span class="farmer-read-indicator">Read</span>@endif
        </div>
        <p class="farmer-notification-message">{{ data_get($notification->data, 'message', 'An update is available for your account.') }}</p>
        @if(data_get($notification->data, 'reason'))<p class="farmer-notification-note"><strong>MAO feedback:</strong> {{ data_get($notification->data, 'reason') }}</p>@endif
        @if(data_get($notification->data, 'reference'))<p class="farmer-notification-reference">Reference {{ data_get($notification->data, 'reference') }}</p>@endif
        <div class="farmer-notification-footer">
            <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->format('M d, Y · h:i A') ?: 'Date unavailable' }}</time>
            <a href="{{ $destination }}">{{ $destinationLabel }} <x-ui.icon name="next" size="xs" /></a>
            @if(!$notification->read_at)
                <form method="POST" action="{{ route('farmer.notifications.read', $notification->id) }}">
                    @csrf
                    <button class="farmer-button farmer-button-light" type="submit">Mark as read</button>
                </form>
            @endif
        </div>
    </div>
</article>
