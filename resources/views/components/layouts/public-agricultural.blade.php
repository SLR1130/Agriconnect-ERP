@props(['title' => 'Agricultural services', 'description' => 'Official agricultural programs and service announcements from Bongabong Municipal Agriculture Office.'])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    <title>{{ $title }} · AgriConnect</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="public-agri-body">
    <a class="public-skip-link" href="#main-content">Skip to content</a>
    <header class="public-agri-header">
        <a class="public-agri-brand" href="{{ route('home') }}" aria-label="AgriConnect home">
            <span class="public-agri-mark" aria-hidden="true"><x-ui.icon name="crop" size="lg" /></span>
            <span><strong>AgriConnect</strong><small>Bongabong Municipal Agriculture Office</small></span>
        </a>
        <nav class="public-agri-nav" aria-label="Main navigation">
            <a href="{{ route('public.agricultural-services.index') }}" @if(request()->routeIs('public.agricultural-services.*', 'public.agricultural-programs.*', 'public.announcements.*')) aria-current="page" @endif>Programs &amp; notices</a>
            @auth
                @if(auth()->user()->hasRole('farmer'))<a href="{{ route('farmer.portal') }}">My Farmer Portal</a>@else<a href="{{ route('dashboard') }}">ERP workspace</a>@endif
            @else
                <a href="{{ route('login') }}">Sign in</a>
                <a class="public-agri-nav-cta" href="{{ route('farmer.register') }}">Farmer registration</a>
            @endauth
        </nav>
    </header>
    <main id="main-content" class="public-agri-main">
        @if(session('status'))<div class="farmer-message farmer-message-success" role="status">{{ session('status') }}</div>@endif
        {{ $slot }}
    </main>
    <footer class="public-agri-footer">
        <div><strong>AgriConnect</strong><p>Official agricultural programs and services from the Bongabong Municipal Agriculture Office.</p></div>
        <div class="public-agri-footer-links"><a href="{{ route('public.agricultural-services.index') }}">Programs and notices</a><a href="{{ route('login') }}">Sign in</a><a href="{{ route('farmer.register') }}">Farmer registration</a></div>
        <p class="public-agri-copyright">&copy; {{ now()->year }} Bongabong Municipal Agriculture Office</p>
    </footer>
</body>
</html>
