<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}AgriConnect</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="farmer-body font-sans antialiased">
    @php($noticeCount = isset($unreadCount) ? $unreadCount : auth()->user()->unreadNotifications()->count())
    <a href="#main-content" class="farmer-skip">Skip to content</a>
    <header class="farmer-header">
        <div class="farmer-container farmer-header-inner">
            <a href="{{ route('farmer.portal') }}" class="farmer-brand" aria-label="AgriConnect farmer home">
                <span class="farmer-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 21V11m0 5c-5 0-8-3-8-8 5 0 8 3 8 8Zm0-4c0-5 3-8 8-8 0 5-3 8-8 8Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <span><strong>AgriConnect</strong><small>Farmer services</small></span>
            </a>
            <nav class="farmer-desktop-nav" aria-label="Farmer navigation">
                <a href="{{ route('farmer.portal') }}" @if(request()->routeIs('farmer.portal')) aria-current="page" @endif>Home</a>
                <a href="{{ route('farmer.services') }}" @if(request()->routeIs('farmer.services', 'farmer.programs.show', 'farmer.assistance')) aria-current="page" @endif>Explore services</a>
                <a href="{{ route('farmer.farms') }}" @if(request()->routeIs('farmer.farms', 'farmer.production')) aria-current="page" @endif>My farms</a>
                <a href="{{ route('farmer.activities') }}" @if(request()->routeIs('farmer.activities', 'farmer.applications.show', 'farmer.equipment-requests.show')) aria-current="page" @endif>My activities</a>
            </nav>
            <div class="farmer-header-actions">
                <a class="farmer-notice-link" href="{{ route('farmer.notifications') }}" aria-label="Notifications{{ $noticeCount ? ', '.$noticeCount.' unread' : '' }}" @if(request()->routeIs('farmer.notifications')) aria-current="page" @endif>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @if($noticeCount)<span class="farmer-notice-count">{{ $noticeCount > 99 ? '99+' : $noticeCount }}</span>@endif
                </a>
                <details class="farmer-account-menu">
                    <summary aria-label="Account and more services"><span class="farmer-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="farmer-account-name">{{ auth()->user()->name }}</span><span aria-hidden="true">⌄</span></summary>
                    <div class="farmer-account-panel">
                        <p class="farmer-account-caption">Signed in as {{ auth()->user()->name }}</p>
                        <a href="{{ route('farmer.profile') }}">My profile</a>
                        <a href="{{ route('farmer.production') }}">Agricultural records</a>
                        <a href="{{ route('farmer.assistance') }}">Assistance applications</a>
                        <a href="{{ route('farmer.equipment') }}">Equipment services</a>
                        <a href="{{ route('farmer.notifications') }}">Notifications</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
                    </div>
                </details>
            </div>
        </div>
    </header>

    <main id="main-content" class="farmer-container farmer-main" tabindex="-1">
        @if(isset($title))<div class="farmer-page-heading"><p class="farmer-eyebrow">Bongabong agricultural services</p><h1>{{ $title }}</h1>@isset($description)<p>{{ $description }}</p>@endisset</div>@endif
        @if(session('status'))<div class="farmer-message farmer-message-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="farmer-message farmer-message-error" role="alert"><strong>Please check your information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        {{ $slot }}
    </main>

    <footer class="farmer-footer"><div class="farmer-container"><span>AgriConnect · Bongabong Municipal Agriculture Office</span><span>For assistance, contact your Municipal Agriculture Office.</span></div></footer>
    <nav class="farmer-bottom-nav" aria-label="Mobile farmer navigation">
        <a href="{{ route('farmer.portal') }}" @if(request()->routeIs('farmer.portal')) aria-current="page" @endif><span aria-hidden="true">⌂</span>Home</a>
        <a href="{{ route('farmer.services') }}" @if(request()->routeIs('farmer.services', 'farmer.programs.show', 'farmer.assistance')) aria-current="page" @endif><span aria-hidden="true">✦</span>Services</a>
        <a href="{{ route('farmer.farms') }}" @if(request()->routeIs('farmer.farms', 'farmer.production')) aria-current="page" @endif><span aria-hidden="true">♧</span>Farms</a>
        <a href="{{ route('farmer.activities') }}" @if(request()->routeIs('farmer.activities', 'farmer.applications.show', 'farmer.equipment-requests.show')) aria-current="page" @endif><span aria-hidden="true">☷</span>Activity</a>
    </nav>
</body>
</html>
