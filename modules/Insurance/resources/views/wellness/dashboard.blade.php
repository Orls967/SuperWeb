@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="sm:flex sm:items-center sm:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Program Wellness & Healthy Rewards</h1>
            <p class="mt-2 text-sm text-gray-700">Pelacakan gaya hidup sehat, deteksi integritas sensor anti-gaming, dan penghargaan diskon premi.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg p-5">
            <dt class="text-sm font-medium text-gray-500 truncate">Rata-rata Langkah Harian</dt>
            <dd class="mt-1 text-3xl font-semibold text-gray-900">9.450</dd>
        </div>
        <div class="bg-white overflow-hidden shadow rounded-lg p-5">
            <dt class="text-sm font-medium text-gray-500 truncate">Skor Kesehatan Rata-rata</dt>
            <dd class="mt-1 text-3xl font-semibold text-green-600">88 / 100</dd>
        </div>
        <div class="bg-white overflow-hidden shadow rounded-lg p-5">
            <dt class="text-sm font-medium text-gray-500 truncate">Diskon Premi Aktif</dt>
            <dd class="mt-1 text-3xl font-semibold text-indigo-600">5.0%</dd>
        </div>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Log Aktivitas Telemetri Terkini</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Hasil verifikasi sensor biosensor dan audit integritas data.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Langkah</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detak Jantung</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tidur (Mnt)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Skor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status Anti-Gaming</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($logs as $log)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $log->log_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ number_format($log->daily_steps, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $log->avg_heart_rate_bpm }} bpm</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $log->sleep_minutes }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-green-600">{{ $log->health_score }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $log->is_flagged_gaming ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                {{ $log->is_flagged_gaming ? 'FLAGGED ANOMALY' : 'VERIFIED GENUINE' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada data aktivitas wellness.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

