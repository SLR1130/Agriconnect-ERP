<x-layouts.app>
    <x-slot name='header'>Module Management</x-slot>
    <x-slot name='description'>Manage the six registered agricultural modules and their shared ERP access assignments.</x-slot>

    <div class='grid grid-cols-1 xl:grid-cols-2 gap-6'>
        @forelse($modules as $module)
            <div class='bg-white rounded-xl border border-gray-200 p-6'>
                <div class='flex items-start justify-between gap-4'>
                    <div>
                        <h2 class='text-lg font-semibold text-dark-slate'>{{ $module->name }}</h2>
                        <p class='text-sm text-gray-500 mt-1'>{{ strtoupper($module->slug) }} - {{ $module->developer ?: 'Unassigned developer' }}</p>
                    </div>
                    <span class='px-2.5 py-1 rounded-full text-xs {{ $module->is_active ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}'>
                        {{ $module->is_active ? 'Active' : 'In development' }}
                    </span>
                </div>
                <p class='text-sm text-gray-600 mt-4'>{{ $module->description }}</p>
                <div class='mt-4 text-sm text-gray-600'>{{ $module->module_access_count }} role access assignment(s)</div>

                @if(auth()->user()?->isSuperAdministrator())
                    <form method='POST' action='{{ route('erp.modules.update', $module) }}' class='mt-5 grid grid-cols-1 md:grid-cols-3 gap-3 items-end'>
                        @csrf
                        @method('PATCH')
                        <label class='text-sm text-gray-700'>Progress
                            <input type='number' name='development_progress' min='0' max='100' value='{{ $module->development_progress }}' class='mt-1 w-full rounded-lg border-gray-300'>
                        </label>
                        <label class='text-sm text-gray-700'>Enabled
                            <select name='is_enabled' class='mt-1 w-full rounded-lg border-gray-300'>
                                <option value='1' @selected($module->is_enabled)>Yes</option>
                                <option value='0' @selected(!$module->is_enabled)>No</option>
                            </select>
                        </label>
                        <label class='text-sm text-gray-700'>Active
                            <select name='is_active' class='mt-1 w-full rounded-lg border-gray-300'>
                                <option value='1' @selected($module->is_active)>Yes</option>
                                <option value='0' @selected(!$module->is_active)>No</option>
                            </select>
                        </label>
                        <button class='md:col-span-3 justify-self-end px-4 py-2 rounded-lg bg-emerald-green text-white text-sm'>Save module settings</button>
                    </form>
                @endif
            </div>
        @empty
            <div class='xl:col-span-2 bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-600'>No modules are registered yet.</div>
        @endforelse
    </div>
</x-layouts.app>
