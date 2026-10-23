@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Academy & LMS Cohort Operations</h2>
            <p class="mt-1 text-sm text-gray-400">Manajemen Kurikulum, Cohort Pelatihan & Pendaftaran Siswa</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Programs -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Program Pendidikan ({{ $programs->count() }})</h3>
                    <div class="space-y-2">
                        @forelse($programs as $program)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="text-white font-medium">{{ $program->title }}</div>
                                <div class="text-xs text-gray-400">Kode: {{ $program->program_code }} • Sektor: {{ $program->industry_sector }}</div>
                                <div class="text-xs text-indigo-400 mt-1">Biaya: Rp {{ number_format($program->tuition_fee_minor, 0, ',', '.') }}</div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada program aktif.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Cohorts -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Cohort Aktif ({{ $cohorts->count() }})</h3>
                    <div class="space-y-2">
                        @forelse($cohorts as $cohort)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="flex justify-between items-center">
                                    <span class="text-white font-medium">{{ $cohort->cohort_code }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-indigo-900 text-indigo-200">{{ $cohort->status }}</span>
                                </div>
                                <div class="text-xs text-gray-400 mt-1">Kapasitas: {{ $cohort->enrolled_count }} / {{ $cohort->max_capacity }}</div>
                                <a href="{{ route('edu.academy.show', $cohort->id) }}" class="inline-block mt-2 text-xs text-indigo-400 hover:underline">Lihat Detail Cohort &rarr;</a>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada cohort.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Recent Enrollments -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Pendaftaran Terakhir ({{ $enrollments->count() }})</h3>
                    <div class="space-y-2">
                        @forelse($enrollments as $enrollment)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="text-white text-sm font-mono">{{ $enrollment->enrollment_code }}</div>
                                <div class="text-xs text-gray-400">Siswa: {{ $enrollment->student_party_id }}</div>
                                <div class="text-xs text-emerald-400 mt-1">Status: {{ $enrollment->status }}</div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada pendaftaran.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
