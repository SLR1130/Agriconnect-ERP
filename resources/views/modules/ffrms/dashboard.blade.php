{{-- Extend removed for setup phase --}}

@section('content')
    <!-- FFRMS Specific Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <x-stat-card 
            title="Total Farmers" 
            value="1,247"
            icon="users"
            trend="+12%"
            trend-up="true"
            color="emerald-green">
            <p class="text-sm text-gray-600">Registered individuals</p>
        </x-components.stat-card>

        <x-stat-card 
            title="Total Farms" 
            value="892"
            icon="farm"
            trend="+8%"
            trend-up="true"
            color="forest-green">
            <p class="text-sm text-gray-600">Agricultural properties</p>
        </x-components.stat-card>

        <x-stat-card 
            title="Barangays Covered" 
            value="24"
            icon="location"
            trend="+2"
            trend-up="true"
            color="agricultural-gold">
            <p class="text-sm text-gray-60">Out of 25 total</p>
        </x-components.stat-card>
    </div>

    <!-- FFRMS Specific Content -->
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-dark-slate mb-4">Farmer Registration Overview</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Registration Status -->
            <div class="bg-light-sage rounded-lg p-4">
                <h4 class="font-medium text-dark-slate mb-3">Registration Status</h4>
                <div class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-600">Active Registrations</span>
                            <span class="text-sm font-medium text-emerald-green">1,124</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-emerald-green h-2 rounded-full" style="width: 90%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-600">Pending Renewals</span>
                            <span class="text-sm font-medium text-amber-600">89</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-amber-500 h-2 rounded-full" style="width: 7%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-600">Inactive</span>
                            <span class="text-sm font-medium text-gray-600">34</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-gray-400 h-2 rounded-full" style="width: 3%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Land Distribution -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h4 class="font-medium text-dark-slate mb-3">Land Distribution by Type</h4>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Rice Fields</span>
                        <span class="text-sm font-medium text-dark-slate">425 farms</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Corn Fields</span>
                        <span class="text-sm font-medium text-dark-slate">238 farms</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Vegetable Gardens</span>
                        <span class="text-sm font-medium text-dark-slate">156 farms</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Fruit Orchards</span>
                        <span class="text-sm font-medium text-dark-slate">73 farms</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Farmer Registrations -->
        <div class="mt-8">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-medium text-dark-slate">Recent Farmer Registrations</h4>
                <a href="#" class="text-sm text-emerald-green hover:text-emerald-green/80 font-medium">
                    View All →
                </a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barangay</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Farm Size</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Crops</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @for($i = 1; $i <= 5; $i++)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">Juan Dela Cruz {{ $i }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">San Isidro</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ rand(1, 10) }} hectares</td>
                            <td class="px-4 py-3 text-sm text-gray-600">Rice, Corn</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                            </td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
