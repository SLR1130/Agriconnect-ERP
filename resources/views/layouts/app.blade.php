<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'AgriConnect ERP') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="erp-body font-sans antialiased">
    @php
        $currentUser = auth()->user();
        $rolePriority = ['super_admin', 'mao_admin', 'municipal_agriculturist', 'module_personnel', 'authorized_viewer', 'farmer'];
        $currentRole = $currentUser?->roles?->sortBy(fn ($role) => array_search($role->slug, $rolePriority, true) === false ? 99 : array_search($role->slug, $rolePriority, true))->first();
        $currentRoleName = $currentRole?->name ?: 'Authorized account';
        $currentInitials = collect(preg_split('/\s+/', trim((string) ($currentUser?->name ?: 'User'))))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
    @endphp

    <div class="erp-shell">
        @include('components.sidebar', ['currentUser' => $currentUser, 'currentRoleName' => $currentRoleName, 'currentInitials' => $currentInitials])
        <button id="mobile-sidebar-backdrop" class="erp-backdrop" type="button" hidden aria-label="Close navigation"></button>

        <div class="erp-main">
            @include('components.navbar', ['currentUser' => $currentUser, 'currentRoleName' => $currentRoleName, 'currentInitials' => $currentInitials])

            <main class="erp-content">
                @if(isset($header))
                    <header class="erp-page-heading">
                        <div>
                            <p class="erp-page-eyebrow">AgriConnect ERP</p>
                            <h1 class="erp-page-title">{{ $header }}</h1>
                            @isset($description)<p class="erp-page-description">{{ $description }}</p>@endisset
                        </div>
                        @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
                    </header>
                @endif

                {{ $slot }}
            </main>

            <footer class="erp-footer">
                <span>&copy; {{ date('Y') }} AgriConnect ERP · Bongabong Municipal Agriculture Office</span>
                <span>Version {{ config('agriconnect.version', '1.0.0') }}</span>
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
