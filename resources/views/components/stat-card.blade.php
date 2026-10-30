<div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-shadow duration-200">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">{{ $title }}</h3>
        
        @isset($icon)
            <div class="p-2 rounded-lg bg-{{ $color ?? 'emerald-green' }}/10">
                @if($icon === 'users')
                    <svg class="w-5 h-5 text-{{ $color ?? 'emerald-green' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13 0a4 4 0 00-4-4" />
                    </svg>
                @elseif($icon === 'farm')
                    <svg class="w-5 h-5 text-{{ $color ?? 'emerald-green' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                @elseif($icon === 'programs')
                    <svg class="w-5 h-5 text-{{ $color ?? 'emerald-green' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                @elseif($icon === 'requests')
                    <svg class="w-5 h-5 text-{{ $color ?? 'emerald-green' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                @else
                    <svg class="w-5 h-5 text-{{ $color ?? 'emerald-green' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                @endif
            </div>
        @endisset
    </div>

    <div class="flex items-end justify-between">
        <div>
            <p class="text-3xl font-bold text-dark-slate">{{ $value }}</p>
            
            @isset($trend)
                <div class="flex items-center mt-2">
                    @if($trendUp ?? true)
                        <svg class="w-4 h-4 text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                        </svg>
                        <span class="text-sm font-medium text-green-600">{{ $trend }}</span>
                    @else
                        <svg class="w-4 h-4 text-red-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                        <span class="text-sm font-medium text-red-600">{{ $trend }}</span>
                    @endif
                    <span class="text-sm text-gray-500 ml-2">from last month</span>
                </div>
            @endisset
        </div>

        {{ $slot }}
    </div>
</div>