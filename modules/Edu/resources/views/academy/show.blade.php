@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-white">Detail Cohort: {{ $cohort->cohort_code }}</h2>
                    <p class="mt-1 text-sm text-gray-400">Program: {{ $program->title ?? 'N/A' }} ({{ $program->program_code ?? '-' }})</p>
                </div>
                <a href="{{ route('edu.academy.index') }}" class="text-xs text-gray-400 hover:text-white">&larr; Kembali</a>
            </div>

            <div class="mt-6 bg-gray-700/50 p-6 rounded-lg">
                <h3 class="text-md font-medium text-white mb-4">Pendaftaran Siswa Baru ke Cohort</h3>
                <form action="{{ route('edu.academy.enroll') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="cohort_id" value="{{ $cohort->id }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Student Party ID</label>
                            <input type="text" name="student_party_id" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Corporate Client ID (Opsional)</label>
                            <input type="text" name="corporate_client_id" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                        </div>
                    </div>
                    <button type="submit" class="py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded transition">
                        Daftarkan Siswa
                    </button>
                </form>
            </div>

            <div class="mt-6">
                <h3 class="text-md font-medium text-white mb-3">Daftar Siswa Terdaftar ({{ $enrollments->count() }})</h3>
                <div class="space-y-2">
                    @forelse($enrollments as $enr)
                        <div class="p-3 bg-gray-700/50 rounded border border-gray-600 flex justify-between items-center">
                            <div>
                                <span class="font-mono text-sm text-white">{{ $enr->enrollment_code }}</span>
                                <span class="text-xs text-gray-400 ml-2">ID Siswa: {{ $enr->student_party_id }}</span>
                            </div>
                            <span class="text-xs px-2 py-1 rounded bg-emerald-900 text-emerald-200">{{ $enr->status }}</span>
                        </div>
                    @empty
                        <div class="text-gray-400 text-sm">Belum ada siswa di cohort ini.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
