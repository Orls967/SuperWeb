<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Transkrip Nilai Akademik Mahasiswa') }}
        </h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mata Kuliah</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Semester</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nilai Mutu</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bobot (IP)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hash Sertifikat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($transcripts as $t)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-bold">{{ $t->course->name ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $t->term->name ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-semibold">{{ $t->letter_grade }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ number_format($t->grade_point, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-gray-600">{{ substr($t->certificate_hash, 0, 16) }}...</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Belum ada nilai terkunci.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

