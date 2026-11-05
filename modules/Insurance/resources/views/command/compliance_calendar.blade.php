@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Kalender Kepatuhan & Batas Pelaporan Regulator (OJK / APPARDI)</h1>
            <p class="text-sm text-gray-600">Jadwal pelaporan berkala solvabilitas RBC, audit aktuaria tahunan & sanksi denda keterlambatan</p>
        </div>
        <a href="{{ route('insurance.command.dashboard') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Command Center</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Tahun Kalender</span>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $calendar['calendar_year'] ?? 2026 }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Kewajiban Pelaporan</span>
            <p class="text-2xl font-bold text-indigo-600 mt-2">{{ $calendar['total_obligations'] ?? 0 }} Agenda</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Jatuh Tempo Segera (< 30 Hari)</span>
            <p class="text-2xl font-bold text-amber-600 mt-2">{{ $calendar['upcoming_count'] ?? 0 }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Terlambat (Overdue)</span>
            <p class="text-2xl font-bold text-rose-600 mt-2">{{ $calendar['overdue_count'] ?? 0 }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode Laporan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Kewajiban Regulasi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Regulator</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Batas Waktu (Due Date)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Denda Keterlambatan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach(($calendar['schedule'] ?? []) as $item)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-gray-900">{{ $item['code'] }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $item['title'] }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $item['regulator'] }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ $item['due_date'] }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-rose-600">Rp {{ number_format($item['penalty_per_day_idr'], 0, ',', '.') }} / hari</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $item['status'] === 'OVERDUE' ? 'bg-rose-100 text-rose-800' : ($item['status'] === 'DUE_SOON' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">
                            {{ $item['status'] }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

