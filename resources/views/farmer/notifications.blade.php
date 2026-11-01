<x-layouts.farmer>
    <x-slot name="title">Notifications</x-slot>
    <x-slot name="description">Messages from the MAO about your registration and agricultural services.</x-slot>

    <form method="GET" action="{{ route('farmer.notifications') }}" class="farmer-notification-filters" aria-label="Filter notifications">
        <div class="farmer-field">
            <label for="notification-read">Read status</label>
            <select id="notification-read" name="read">
                <option value="">All messages</option>
                <option value="unread" @selected($readFilter === 'unread')>Unread</option>
                <option value="read" @selected($readFilter === 'read')>Read</option>
            </select>
        </div>
        <div class="farmer-field">
            <label for="notification-category">Service category</label>
            <select id="notification-category" name="category">
                <option value="">All services</option>
                @foreach(['assistance' => 'Assistance', 'production' => 'Production', 'equipment' => 'Equipment', 'registration' => 'Registration', 'announcements' => 'Official announcements', 'other' => 'Other updates'] as $value => $label)
                    <option value="{{ $value }}" @selected($categoryFilter === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="farmer-notification-filter-actions">
            <button class="farmer-button" type="submit">Apply filters</button>
            <a class="farmer-button farmer-button-light" href="{{ route('farmer.notifications') }}">Clear</a>
        </div>
    </form>

    <section class="farmer-notification-results" aria-labelledby="notification-results-title">
        <div class="farmer-activity-results-heading">
            <h2 id="notification-results-title">Your messages</h2>
            <p>{{ $notifications->total() }} message{{ $notifications->total() === 1 ? '' : 's' }}</p>
        </div>
        @if($notifications->count())
            <ol class="farmer-notification-list">
                @foreach($notifications as $notification)
                    <li><x-farmer.notification-item :notification="$notification" /></li>
                @endforeach
            </ol>
            <nav class="farmer-activity-pagination" aria-label="Notification pages">{{ $notifications->links() }}</nav>
        @else
            <x-ui.empty-state
                :title="($readFilter || $categoryFilter) ? 'No matching messages' : 'No notifications yet'"
                :description="($readFilter || $categoryFilter) ? 'Try another read status or service category.' : 'Messages about verification and service updates will appear here when the MAO sends them.'"
                icon="notifications"
            >
                @if($readFilter || $categoryFilter)
                    <a class="farmer-button farmer-button-light" href="{{ route('farmer.notifications') }}">Clear filters</a>
                @endif
            </x-ui.empty-state>
        @endif
    </section>
</x-layouts.farmer>
