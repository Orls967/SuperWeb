@extends('layouts.app')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dead-Letter Queue & Karantina Poison</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Inspeksi, pemutaran ulang, dan isolasi pesan gagal pada Universal Event Spine.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.spine.dlq.export') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50">
                Ekspor CSV DLQ
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md text-sm text-green-800 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-md text-sm text-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <!-- Navigasi Tab -->
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="{{ route('admin.spine.dlq.index', ['tab' => 'dlq']) }}" class="{{ $tab === 'dlq' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Antrean DLQ ({{ $dlqMessages->total() }})
            </a>
            <a href="{{ route('admin.spine.dlq.index', ['tab' => 'poison']) }}" class="{{ $tab === 'poison' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Karantina Poison Messages ({{ $poisonMessages->total() }})
            </a>
        </nav>
    </div>

    @if ($tab === 'dlq')
        <!-- Formulir Filter & Aksi Batch -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
            <form method="GET" action="{{ route('admin.spine.dlq.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <input type="hidden" name="tab" value="dlq">
                <div>
                    <label for="consumer_group" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Consumer Group</label>
                    <input type="text" id="consumer_group" name="consumer_group" value="{{ $filters['consumer_group'] ?? '' }}" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700" placeholder="Semua grup">
                </div>
                <div>
                    <label for="topic" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Topic</label>
                    <input type="text" id="topic" name="topic" value="{{ $filters['topic'] ?? '' }}" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700" placeholder="Semua topik">
                </div>
                <div>
                    <label for="search" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cari Kata Kunci</label>
                    <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700" placeholder="ID atau galat">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Terapkan Filter
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.spine.dlq.replay-batch') }}" class="border-t border-gray-200 dark:border-gray-700 pt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                @csrf
                <div class="text-sm text-gray-500">Pilih pesan di tabel lalu klik aksi massal di bawah:</div>
                <div class="flex items-center gap-3">
                    <input type="text" name="reason" required placeholder="Alasan operasional" class="text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700" aria-label="Alasan replay batch">
                    <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded-md text-white bg-emerald-600 hover:bg-emerald-700">
                        Putar Ulang Terpilih
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabel DLQ -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Message ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grup & Topik</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Percobaan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Galat Terakhir</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($dlqMessages as $msg)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-500">#{{ $msg->id }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100">{{ Str::limit($msg->message_id, 14) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $msg->consumer_group }}</div>
                                    <div class="text-xs text-gray-400">{{ $msg->topic }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-red-600 font-semibold">{{ $msg->attempts }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 max-w-xs truncate" title="{{ $msg->last_error }}">
                                    {{ $msg->last_error }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm space-x-2">
                                    <form method="POST" action="{{ route('admin.spine.dlq.replay') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $msg->id }}">
                                        <input type="hidden" name="reason" value="Manual single replay">
                                        <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Replay</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.spine.dlq.discard') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $msg->id }}">
                                        <input type="hidden" name="reason" value="Discarded by operator">
                                        <button type="submit" class="text-xs text-red-600 hover:text-red-900 font-medium">Discard</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Tidak ada pesan yang tertahan di Dead-Letter Queue.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($dlqMessages->hasPages())
                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $dlqMessages->links() }}
                </div>
            @endif
        </div>
    @else
        <!-- Tabel Karantina Poison Messages -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Message ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grup / Event</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penyebab Karantina</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu Karantina</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($poisonMessages as $poison)
                            <tr>
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100">{{ Str::limit($poison->message_id, 16) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $poison->consumer_group }}</div>
                                    <div class="text-xs text-gray-400">{{ $poison->event_name ?? $poison->topic }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-amber-700 dark:text-amber-400 max-w-sm truncate" title="{{ $poison->failure_reason }}">
                                    {{ $poison->failure_reason }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $poison->quarantined_at }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">Tidak ada pesan yang sedang dalam karantina poison.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($poisonMessages->hasPages())
                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $poisonMessages->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection

