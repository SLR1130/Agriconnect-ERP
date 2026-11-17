<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'AgriConnect ERP') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Styles -->
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    
    <!-- Custom CSS for AgriConnect Theme -->
    <style>
        :root {
            --color-forest-green: #163D2A;
            --color-emerald-green: #237A52;
            --color-agricultural-gold: #D5AB50;
            --color-light-sage: #EAF2E8;
            --color-off-white: #F8FAF7;
            --color-dark-slate: #26352D;
        }
        
        .bg-forest-green { background-color: var(--color-forest-green); }
        .text-forest-green { color: var(--color-forest-green); }
        
        .bg-emerald-green { background-color: var(--color-emerald-green); }
        .text-emerald-green { color: var(--color-emerald-green); }
        
        .bg-agricultural-gold { background-color: var(--color-agricultural-gold); }
        .text-agricultural-gold { color: var(--color-agricultural-gold); }
        
        .bg-light-sage { background-color: var(--color-light-sage); }
        .text-light-sage { color: var(--color-light-sage); }
        
        .bg-off-white { background-color: var(--color-off-white); }
        .text-off-white { color: var(--color-off-white); }
        
        .bg-dark-slate { background-color: var(--color-dark-slate); }
        .text-dark-slate { color: var(--color-dark-slate); }
    </style>
</head>
<body class="font-sans antialiased bg-gradient-to-br from-light-sage to-off-white">
    <div class="min-h-screen flex flex-col items-center justify-center p-6">
        <!-- Logo and Branding -->
        <div class="mb-8 text-center">
            <div class="flex items-center justify-center mb-4">
                <div class="w-16 h-16 rounded-full bg-emerald-green flex items-center justify-center">
                    <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>
            <h1 class="text-3xl font-bold text-dark-slate">AgriConnect ERP</h1>
            <p class="text-gray-600 mt-2">Agricultural Management System for Bongabong Municipal Agriculture Office</p>
        </div>

        <!-- Page Content -->
        <div class="w-full max-w-md">
            {{ $slot }}
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-600">
            <p>&copy; {{ date('Y') }} Bongabong Municipal Agriculture Office</p>
            <p class="mt-1">Enterprise Resource Planning System for Agricultural Management</p>
        </div>
    </div>
</body>
</html>
