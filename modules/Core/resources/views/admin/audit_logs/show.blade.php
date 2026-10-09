@extends('layouts.app')

@section('title', 'Detail Audit Log #' . $auditLog->id)

@section('content')
<div class="max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('admin.audit-logs.index') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1 mb-2">
            &larr; Kembali ke Daftar Audit Trail
        </a>
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Log Audit #{{ $auditLog->id }}
            </h1>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                Append-Only Verified
            </span>
        </div>
    </div>

    <!-- Metadata Card -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6 border border-gray-100 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-gray-700 pb-2">
            Informasi Aksi & Konteks
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-semibold">Nama Aksi</span>
                <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ $auditLog->action }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-semibold">Tipe Impact</span>
                <span class="font-semibold text-gray-900 dark:text-white">{{ ucfirst($auditLog->impact_type ?? 'N/A') }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-semibold">Waktu Eksekusi</span>
                <span class="text-gray-900 dark:text-white">{{ $auditLog->created_at ? $auditLog->created_at->format('Y-m-d H:i:s T') : '—' }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-semibold">Correlation ID</span>
                <span class="font-mono text-gray-900 dark:text-white">{{ $auditLog->correlation_id ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-semibold">Pengguna / Aktor</span>
                <span class="text-gray-900 dark:text-white">
                    @if($auditLog->user)
                        {{ $auditLog->user->name }} (ID: {{ $auditLog->user->id }}, {{ $auditLog->user->email }})
                    @else
                        <span class="italic text-gray-400">Sistem / Background Job</span>
                    @endif
                </span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-semibold">Target Entitas</span>
                <span class="font-mono text-gray-900 dark:text-white">
                    @if($auditLog->auditable_type)
                        {{ $auditLog->auditable_type }} #{{ $auditLog->auditable_id }}
                    @else
                        —
                    @endif
                </span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-semibold">Alamat IP</span>
                <span class="font-mono text-gray-900 dark:text-white">{{ $auditLog->ip_address ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-semibold">User Agent</span>
                <span class="text-xs text-gray-600 dark:text-gray-300 break-all">{{ $auditLog->user_agent ?? '—' }}</span>
            </div>
        </div>
    </div>

    <!-- Comparison: Old vs New Values -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 border border-red-100 dark:border-red-950/40">
            <h3 class="text-sm font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Nilai Sebelum (Old Values)
            </h3>
            <pre class="bg-gray-50 dark:bg-gray-900 p-4 rounded text-xs font-mono text-gray-800 dark:text-gray-200 overflow-x-auto border border-gray-200 dark:border-gray-800">{{ json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 border border-emerald-100 dark:border-emerald-950/40">
            <h3 class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Nilai Sesudah (New Values)
            </h3>
            <pre class="bg-gray-50 dark:bg-gray-900 p-4 rounded text-xs font-mono text-gray-800 dark:text-gray-200 overflow-x-auto border border-gray-200 dark:border-gray-800">{{ json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
        </div>
    </div>

    <!-- Extra Context -->
    @if(!empty($auditLog->context))
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 border border-gray-100 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
            Payload Konteks Tambahan
        </h3>
        <pre class="bg-gray-50 dark:bg-gray-900 p-4 rounded text-xs font-mono text-gray-800 dark:text-gray-200 overflow-x-auto border border-gray-200 dark:border-gray-800">{{ json_encode($auditLog->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
    @endif
</div>
@endsection
