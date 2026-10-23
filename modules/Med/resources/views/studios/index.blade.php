@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Studio Content Production & Greenlight Pipeline</h2>
            <p class="mt-1 text-sm text-gray-400">Manajemen Fasilitas Studio, Proyek Produksi Kreatif & Alokasi Anggaran</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Studios -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Fasilitas Studio & Sound Stage ({{ $studios->count() }})</h3>
                    <div class="space-y-3">
                        @forelse($studios as $studio)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $studio->name }} ({{ $studio->studio_code }})</div>
                                    <div class="text-xs text-gray-400">{{ $studio->facility_type }} • {{ $studio->location_city }}</div>
                                    <div class="text-xs text-indigo-400 mt-1">Tarif Harian: Rp {{ number_format($studio->full_day_rate_minor, 0, ',', '.') }}</div>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded bg-emerald-900 text-emerald-200">{{ $studio->status }}</span>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada studio terdaftar.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Projects -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Proyek Film & Serial Aktif ({{ $projects->count() }})</h3>
                    <div class="space-y-3">
                        @forelse($projects as $project)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="text-white font-medium">{{ $project->title }}</div>
                                        <div class="text-xs text-gray-400">Kode: {{ $project->project_code }} • Genre: {{ $project->genre }}</div>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded bg-indigo-900 text-indigo-200">{{ $project->phase }}</span>
                                </div>
                                <div class="text-xs text-gray-300 mt-2">
                                    Budget: Rp {{ number_format($project->budget_limit_minor, 0, ',', '.') }}
                                </div>
                                <a href="{{ route('med.studios.show', $project->id) }}" class="inline-block mt-2 text-xs text-indigo-400 hover:underline">Kelola & Greenlight &rarr;</a>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada proyek aktif.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
