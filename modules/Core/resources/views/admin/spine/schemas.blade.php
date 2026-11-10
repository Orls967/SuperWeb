@extends('layouts.app')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pusat Registrasi Skema Event (Schema Registry)</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Verifikasi backward compatibility, penegakan aturan aditif A6, dan audit skema.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.spine.schemas.export') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50">
                Ekspor CSV Skema
            </a>
        </div>
    </div>

    <!-- Daftar Skema -->
    <div class="space-y-6">
        @forelse ($grouped as $eventName => $versions)
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-gray-200 dark:border-gray-700 pb-3 mb-4 gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ $eventName }}</h2>
                        <p class="text-xs text-gray-500">Produsen: {{ $versions->first()->producer_module }} | Konsumen: {{ implode(', ', (array) $versions->first()->consumer_modules) }}</p>
                    </div>
                    <div class="text-xs font-semibold px-2.5 py-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded">
                        {{ $versions->count() }} Versi Terdaftar
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Versi</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status Kompatibilitas A6</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Waktu Pembaruan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($versions as $ver)
                                @php
                                    $eval = $evaluations[$ver->id] ?? ['is_compatible' => true, 'violations' => []];
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 text-sm font-bold text-gray-900 dark:text-white">v{{ $ver->version }}</td>
                                    <td class="px-3 py-2 text-sm">
                                        @if ($eval['is_compatible'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                                Aditif Kompatibel (A6 Lolos)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300" title="{{ json_encode($eval['violations']) }}">
                                                Melanggar Kompatibilitas
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $ver->description ?? '-' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $ver->updated_at }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center text-sm text-gray-500">
                Belum ada skema event yang tersimpan di Schema Registry.
            </div>
        @endforelse
    </div>
</div>
@endsection

