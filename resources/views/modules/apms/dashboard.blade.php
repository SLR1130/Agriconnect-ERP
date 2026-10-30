@extends('modules.dashboard')

@section('content')
    <!-- APMS Specific Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <x-stat-card 
            title="Active Crops" 
            value="58"
            icon="crop"
            trend="+5"
            trend-up="true"
            color="emerald-green">
            <p class="text-sm text-gray-600">Currently monitored</p>
        </x-components.stat-card>

        <x-stat-card 
            title="Harvest Yield" 
            value="1.2M kg"
            icon="harvest"
            trend="+8%"
            trend-up="true"
            color="forest-green">
            <p class="text-sm text-gray-600">This quarter</p>
        </x-components.stat-card>

        <x-stat-card 
            title="Livestock Count" 
            value="3,456"
            icon="livestock"
            trend="+12%"
            trend-up="true"
            color="agricultural-gold">
            <p class="text-sm text-gray-60">Animals tracked</p>
        </x-components.stat-card>
    </div>

    <!-- APMS Specific Content -->
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-dark-slate mb-4">Production Monitoring Overview</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Crop Distribution -->
            <div class="bg-light-sage rounded-lg p-4">
                <h4 class="font-medium text-dark-slate mb-3">Crop Distribution</h4>
                <div class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-600">Rice Production</span>
                            <span class="text-sm font-medium text-emerald-green">850,000 kg</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-emerald-green h-2 rounded-full" style="width: 70%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-600">Corn Production</span>
                            <span class="text-sm font-medium text-amber-600">250,000 kg</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-amber-500 h-2 rounded-full" style="width: 21%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-600">Vegetables</span>
                            <span class="text-sm font-medium text-blue-600">100,000 kg</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" style="width: 8%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Production Schedule -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h4 class="font-medium text-dark-slate mb-3">Upcoming Harvest Schedule</h4>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Rice - San Isidro</span>
                        <span class="text-sm font-medium text-dark-slate">Nov 15, 2026</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Corn - Maligaya</span>
                        <span class="text-sm font-medium text-dark-slate">Nov 22, 2026</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Tomatoes - Poblacion</span>
                        <span class="text-sm font-medium text-dark-slate">Dec 5, 2026</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Eggplants - San Jose</span>
                        <span class="text-sm font-medium text-dark-slate">Dec 12, 2026</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Production Records -->
        <div class="mt-8">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-medium text-dark-slate">Recent Production Records</h4>
                <a href="#" class="text-sm text-emerald-green hover:text-emerald-green/80 font-medium">
                    View All →
                </a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Farmer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Crop</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Yield</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @for($i = 1; $i <= 5; $i++)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">Maria Santos {{ $i }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">Rice</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ rand(2, 8) }} hectares</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ rand(3000, 8000) }} kg</td>
                            <td class="px-4 py-3 text-sm text-gray-600">Oct {{ 20 + $i }}, 2026</td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
