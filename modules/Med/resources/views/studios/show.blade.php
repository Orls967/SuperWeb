@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-white">Detail Proyek: {{ $project->title }}</h2>
                    <p class="mt-1 text-sm text-gray-400">Kode: {{ $project->project_code }} • Fase Saat Ini: {{ $project->phase }} • Genre: {{ $project->genre }}</p>
                </div>
                <a href="{{ route('med.studios.index') }}" class="text-xs text-gray-400 hover:text-white">&larr; Kembali</a>
            </div>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 bg-gray-700/50 p-6 rounded-lg">
                <h3 class="text-md font-medium text-white mb-4">Persetujuan Anggaran Produksi (Greenlight)</h3>
                @if($project->phase === 'BRIEF')
                    <form action="{{ route('med.studios.greenlight', $project->id) }}" method="POST" class="flex gap-4 items-end">
                        @csrf
                        <div class="flex-1">
                            <label class="block text-xs text-gray-300 mb-1">Batas Anggaran Produksi (Minor)</label>
                            <input type="number" name="budget_limit_minor" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="500000000">
                        </div>
                        <button type="submit" class="py-2 px-6 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded transition">
                            Greenlight Proyek
                        </button>
                    </form>
                @else
                    <div class="text-emerald-400 text-sm">
                        Proyek telah disetujui (Greenlit) dengan anggaran limit Rp {{ number_format($project->budget_limit_minor, 0, ',', '.') }}.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
