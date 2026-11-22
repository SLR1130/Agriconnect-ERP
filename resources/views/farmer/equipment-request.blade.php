@php
    $equipmentEvents = $equipmentRequest->transactions->map(function ($transaction) {
        $title = match ($transaction->action) {
            'requested' => 'Equipment request submitted',
            'approved' => 'MAO approved the request',
            'rejected' => 'MAO declined the request',
            'issued' => 'Equipment issued',
            'returned' => 'Equipment return recorded',
            'cancelled' => 'Request cancelled',
            default => str($transaction->action)->replace('_', ' ')->headline()->toString(),
        };

        return ['title' => $title, 'status' => $transaction->action, 'date' => $transaction->created_at, 'notes' => $transaction->notes, 'condition' => $transaction->condition];
    })->values();
    [$guidance, $tone] = match ($equipmentRequest->status) {
        'pending' => ['Your request is waiting for MAO review. The requested schedule is not confirmed until approved.', 'info'],
        'approved' => ['The MAO approved the schedule. Watch your notifications for an equipment issue update.', 'success'],
        'rejected' => ['The MAO did not approve this request. Review the recorded reason and contact the office if you need help.', 'danger'],
        'issued' => ['The equipment has been issued. Follow the approved schedule and coordinate the return with the MAO.', 'warning'],
        'returned' => ['The MAO recorded the equipment return. See the recorded condition and notes in the history.', 'success'],
        'cancelled' => ['This request was cancelled. No further action is required.', 'info'],
        default => ['The status above reflects the latest request record. Review the recorded transactions below.', 'info'],
    };
@endphp

<x-layouts.farmer>
    <x-slot name="title">Equipment request</x-slot>
    <x-slot name="description">Track the MAO review, approved schedule, issue and return of requested equipment.</x-slot>

    <x-farmer.tracking-header
        service="Equipment services"
        :title="$equipmentRequest->asset->name"
        :status="$equipmentRequest->status"
        :submitted-at="$equipmentRequest->created_at"
        :reference="$equipmentRequest->reference"
        :back-url="route('farmer.equipment')"
        back-label="Back to equipment services"
        icon="equipment"
    />

    <x-farmer.next-action :message="$guidance" :tone="$tone">
        @if(in_array($equipmentRequest->status, ['pending', 'approved'], true))
            <form method="POST" action="{{ route('modules.aeams.requests.cancel', $equipmentRequest) }}">
                @csrf
                <button class="farmer-button farmer-button-light" type="submit">Cancel my request</button>
            </form>
        @endif
    </x-farmer.next-action>

    <div class="farmer-tracking-layout">
        <div class="farmer-tracking-main">
            <section class="farmer-card">
                <div class="farmer-section-heading"><div><h2>Request details</h2><p>{{ $equipmentRequest->asset->category->name ?? 'Agricultural equipment' }}</p></div></div>
                <dl class="farmer-equipment-details">
                    <div><dt>Requested schedule</dt><dd>{{ $equipmentRequest->schedule_start?->timezone(config('agriculture.business_timezone'))->format('M d, Y · h:i A') }} to {{ $equipmentRequest->schedule_end?->timezone(config('agriculture.business_timezone'))->format('M d, Y · h:i A') }} Philippine time</dd></div>
                    <div><dt>Agricultural use</dt><dd>{{ $equipmentRequest->intended_use }}</dd></div>
                    @if($equipmentRequest->review_notes)<div><dt>MAO feedback</dt><dd>{{ $equipmentRequest->review_notes }}</dd></div>@endif
                    @if($equipmentRequest->issued_at)<div><dt>Issued</dt><dd>{{ $equipmentRequest->issued_at->timezone(config('agriculture.business_timezone'))->format('M d, Y · h:i A') }} Philippine time</dd></div>@endif
                    @if($equipmentRequest->returned_at)<div><dt>Returned</dt><dd>{{ $equipmentRequest->returned_at->timezone(config('agriculture.business_timezone'))->format('M d, Y · h:i A') }} Philippine time</dd></div>@endif
                </dl>
            </section>
        </div>
        <aside><x-farmer.status-timeline :events="$equipmentEvents" empty-message="No equipment transactions have been recorded yet." /></aside>
    </div>
</x-layouts.farmer>
