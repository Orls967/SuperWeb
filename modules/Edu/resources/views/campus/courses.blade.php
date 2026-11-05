<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Katalog Mata Kuliah') }}
        </h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama MK</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKS</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prasyarat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($courses as $c)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $c->course_code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-bold">{{ $c->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $c->credits }} SKS</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $c->prerequisite_course_code ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">Belum ada mata kuliah.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

