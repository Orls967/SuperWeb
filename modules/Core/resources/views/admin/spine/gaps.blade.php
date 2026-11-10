@extends('layouts.app')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Celah Urutan Aggregate (Sequence Gaps)</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Pendeteksian celah nomor urut pada aggregate stream menggunakan Algoritma A4.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.spine.gaps.export') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50">
                Ekspor CSV Celah
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md text-sm text-green-800 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    <!-- Kartu Status & Pemicu Scan -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Celah Terbuka (Open)</dt>
            <dd class="mt-1 text-2xl font-semibold text-amber-600">{{ $openCount }}</dd>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Celah Selesai (Resolved)</dt>
            <dd class="mt-1 text-2xl font-semibold text-green-600">{{ $resolvedCount }}</dd>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 flex items-center justify-center">
            <form method="POST" action="{{ route('admin.spine.gaps.scan') }}" class="w-full">
                @csrf
                <button type="submit" class="w-full py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    Pindai Ulang Celah Sekarang
                </button>
            </form>
        </div>
    </div>

    <!-- Tabel Celah -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aggregate Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aggregate ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rentang Celah (Start - End)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu Deteksi</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($gaps as $gap)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-500">#{{ $gap->id }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $gap->aggregate_type }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{{ $gap->aggregate_id }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-amber-600 font-medium">[{{ $gap->gap_start }} - {{ $gap->gap_end }}]</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $gap->status === 'open' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' }}">
                                    {{ $gap->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $gap->detected_at }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                @if ($gap->status === 'open')
                                    <form method="POST" action="{{ route('admin.spine.gaps.resolve') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $gap->id }}">
                                        <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Tutup Celah</button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400">Terselesaikan</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">Tidak ada celah nomor urut yang terdeteksi. Aliran aggregate utuh dan berurutan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($gaps->hasPages())
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                {{ $gaps->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

