<x-layouts.farmer>
    <x-slot name="title">{{ $announcement->title }}</x-slot>
    <x-slot name="description">Official MAO notice · Published {{ $announcement->published_at->format('M d, Y') }}</x-slot>
    <a class="text-sm font-bold text-emerald-700 underline" href="{{ route('farmer.announcements') }}">← All notices</a>
    <article class="farmer-card mt-5"><span class="farmer-pill">Official announcement</span><p class="mt-5 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $announcement->body }}</p>@if($announcement->expires_at)<p class="mt-5 text-sm text-slate-600">This notice is available until {{ $announcement->expires_at->format('M d, Y h:i A') }}.</p>@endif
        @if($announcement->program && $announcement->program->status === 'open')<a class="farmer-button mt-5" href="{{ route('farmer.programs.show', $announcement->program) }}">View related assistance program</a>@endif
    </article>
</x-layouts.farmer>
