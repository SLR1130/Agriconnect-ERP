<x-layouts.app>
    <x-slot name='header'>{{ $title }}</x-slot>
    <x-slot name='description'>{{ $description }}</x-slot>

    <div class='bg-white rounded-xl border border-gray-200 overflow-hidden'>
        @if(session('status'))
            <div class='px-6 py-4 bg-green-50 text-green-800 text-sm'>{{ session('status') }}</div>
        @endif

        @if(count($rows) === 0)
            <div class='p-10 text-center text-gray-600'>No records are available yet.</div>
        @else
            <div class='overflow-x-auto'>
                <table class='min-w-full divide-y divide-gray-200'>
                    <thead class='bg-gray-50'>
                        <tr>
                            @foreach($columns as $column)
                                <th class='px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider'>{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class='divide-y divide-gray-100'>
                        @foreach($rows as $row)
                            <tr class='hover:bg-gray-50'>
                                @foreach($columns as $column)
                                    <td class='px-6 py-4 text-sm text-gray-700'>{{ $row[$column['key']] ?? 'No value' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
