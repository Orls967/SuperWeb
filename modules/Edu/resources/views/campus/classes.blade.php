<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Jadwal Kelas Kuliah') }}
        </h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode Kelas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mata Kuliah</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hari & Jam</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruangan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kapasitas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($classes as $cls)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $cls->class_code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-bold">{{ $cls->course->name ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">Hari {{ $cls->day_of_week }} ({{ sprintf('%02d:%02d', intdiv($cls->start_minute, 60), $cls->start_minute % 60) }} - {{ sprintf('%02d:%02d', intdiv($cls->end_minute, 60), $cls->end_minute % 60) }})</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $cls->room_code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $cls->enrolled_count }} / {{ $cls->max_capacity }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Belum ada kelas terjadwal.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

