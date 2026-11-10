@extends('layouts.app')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Universal Event Spine Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Pusat pemantauan lalu lintas perpesanan event, konsumsi, dan kesehatan sistem.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.spine.dlq.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                Buka Konsol DLQ
            </a>
        </div>
    </div>

    <!-- Metrik Utama -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-5">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Event di Spine</dt>
            <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalEvents) }}</dd>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-5">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pesan DLQ Aktif</dt>
            <dd class="mt-1 text-3xl font-semibold text-red-600 dark:text-red-400">{{ number_format($deadLetters) }}</dd>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-5">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Poison Messages Terisolasi</dt>
            <dd class="mt-1 text-3xl font-semibold text-amber-600 dark:text-amber-400">{{ number_format($poisonMessages) }}</dd>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg p-5">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Nearest-Rank P95 Lag</dt>
            <dd class="mt-1 text-3xl font-semibold text-indigo-600 dark:text-indigo-400">{{ number_format($overallP95Lag) }}</dd>
        </div>
    </div>

    <!-- Pengiriman Terkini -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Pengiriman Event Terkini (Event Deliveries)</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Consumer Group</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Topic</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percobaan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu Pembaruan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($recentDeliveries as $del)
                        <tr>
                            <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100">{{ Str::limit($del->message_id, 16) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $del->consumer_group }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $del->topic }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                    {{ $del->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $del->attempts }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $del->updated_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Belum ada rekaman pengiriman event.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

