<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Rencana Studi Mahasiswa (KRS)') }}
        </h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold mb-4">Semester: {{ $term->name ?? 'Aktif' }}</h3>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kelas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mata Kuliah</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKS</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($enrollments as $enr)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $enr->class->class_code ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-bold">{{ $enr->class->course->name ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $enr->class->course->credits ?? '-' }} SKS</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ strtoupper($enr->status) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">Anda belum memilih kelas KRS.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

