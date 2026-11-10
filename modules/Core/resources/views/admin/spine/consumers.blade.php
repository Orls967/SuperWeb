@extends('layouts.app')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Grup Konsumen & Pemantauan Lag</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Status offset konsumen, analisis nearest-rank p95 lag, dan replay rentang offset.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.spine.consumers.export') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50">
                Ekspor CSV Lag
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md text-sm text-green-800 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    <!-- Formulir Replay Rentang -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Pemutaran Ulang Rentang Offset (Deterministic Replay)</h2>
        <form method="POST" action="{{ route('admin.spine.consumers.replay') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            @csrf
            <div>
                <label for="consumer_group" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Grup Konsumen</label>
                <input type="text" id="consumer_group" name="consumer_group" required placeholder="mis. logistics_worker" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
            </div>
            <div>
                <label for="topic" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Pola Topik</label>
                <input type="text" id="topic" name="topic" value="*" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
            </div>
            <div>
                <label for="from_offset" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Dari Offset</label>
                <input type="number" id="from_offset" name="from_offset" value="0" min="0" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    Jalankan Replay
                </button>
            </div>
        </form>
    </div>

    <!-- Tabel Metrik Konsumen -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Daftar Grup Konsumen Terdaftar</h2>
            <div class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Nearest-Rank P95 Lag: {{ $overallP95Lag }}</div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grup Konsumen</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Topik Langganan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Offset</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Max Offset</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Lag</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu Komit Terakhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($metrics as $m)
                        <tr>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $m['consumer_group'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $m['topic'] }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{{ $m['last_offset'] }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{{ $m['max_offset'] }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $m['current_lag'] > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' }}">
                                    {{ $m['current_lag'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $m['updated_at'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Belum ada grup konsumen yang terdaftar di basis data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

