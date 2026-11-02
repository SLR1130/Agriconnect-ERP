@props(['title' => 'Agricultural services', 'description' => 'Official agricultural programs and service announcements from Bongabong Municipal Agriculture Office.'])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    <title>{{ $title }} | AgriConnect</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="public-agri-body">
    <a class="public-skip-link" href="#main-content">Skip to content</a>
    <x-public.header />
    <main id="main-content" class="public-agri-main {{ request()->routeIs('home') ? 'public-agri-home-main' : '' }}">
        @if(session('status'))<div class="farmer-message farmer-message-success" role="status">{{ session('status') }}</div>@endif
        {{ $slot }}
    </main>
    <x-public.footer />
</body>
</html>
