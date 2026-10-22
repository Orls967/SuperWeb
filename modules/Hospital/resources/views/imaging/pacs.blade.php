@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 min-h-screen text-gray-100">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Header -->
        <div class="p-6 bg-gray-800/80 backdrop-blur rounded-2xl shadow-xl border border-gray-700">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                        <span class="p-2 bg-purple-500/20 text-purple-400 rounded-lg">🩻</span>
                        DICOM PACS & Diagnostic Imaging Modality Suite (Fase 105)
                    </h1>
                    <p class="text-gray-400 text-sm mt-1">Studi Radiologi CT/MRI/USG, SLA Turnaround Radiologist & Penampil Irisan DICOM PACS</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('hospital.laboratory.catalog.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm font-medium transition">
                        ← Katalog Lab LOINC
                    </a>
                </div>
            </div>
        </div>

        <!-- Studi Imaging Radiologi -->
        <div class="p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <span>☢️</span> Daftar Pemeriksaan Radiologi & DICOM Studies
                </h2>
                <span class="text-xs text-gray-400">{{ count($studies) }} studi radiologi</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="bg-gray-900/60 text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="p-3">Study Instance UID</th>
                            <th class="p-3">Pasien</th>
                            <th class="p-3">Modalitas & Organ</th>
                            <th class="p-3">Series / Slice Count</th>
                            <th class="p-3">Turnaround (Menit)</th>
                            <th class="p-3">Status Pembacaan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @forelse($studies as $study)
                            <tr class="hover:bg-gray-700/40">
                                <td class="p-3 font-mono font-medium text-purple-400 text-xs">{{ $study->study_instance_uid }}</td>
                                <td class="p-3">{{ $study->patient->name ?? 'Pasien #' . $study->patient_id }}</td>
                                <td class="p-3 font-medium text-white">
                                    <span class="px-2 py-0.5 text-xs font-bold bg-indigo-900/80 text-indigo-200 rounded mr-1">{{ $study->modality }}</span>
                                    {{ $study->body_part }}
                                </td>
                                <td class="p-3 text-xs">
                                    {{ count($study->series) }} Series ({{ $study->series->sum('slice_count') }} Slices)
                                </td>
                                <td class="p-3 font-mono text-xs">
                                    <span class="{{ ($study->turnaround_minutes ?? 0) <= 60 ? 'text-emerald-400' : 'text-amber-400' }}">
                                        {{ $study->turnaround_minutes ?? 0 }} min
                                    </span>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $study->status === 'COMPLETED' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-amber-950 text-amber-300 border border-amber-800' }}">
                                        {{ $study->status ?? 'PENDING' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-6 text-center text-gray-500">Belum ada studi radiologi DICOM tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
