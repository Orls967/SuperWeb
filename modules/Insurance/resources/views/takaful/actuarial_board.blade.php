@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Laporan Pengawasan Dewan Pengawas Syariah (DPS / DSN-MUI)</h1>
            <p class="text-sm text-gray-600">Verifikasi pemisahan rekening dana tabarru', ketiadaan riba/gharar, dan kepatuhan akad wakalah bil ujrah</p>
        </div>
        <a href="{{ route('insurance.takaful.index') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Portofolio Takaful</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Saldo Dana Tabarru' Terhimpun</span>
            <p class="text-2xl font-bold text-emerald-600 mt-2">Rp {{ number_format($report['fund_segregation']['total_tabarru_balance_idr'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Cadangan Kontinjensi Tabarru'</span>
            <p class="text-2xl font-bold text-indigo-600 mt-2">Rp {{ number_format($report['fund_segregation']['total_contingency_reserve_idr'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Status Opini DPS</span>
            <p class="text-sm font-bold text-emerald-700 mt-2">{{ $report['dps_opinion_status'] ?? 'SESUAI SYARIAH' }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Dana Pemurnian (Social CSR)</span>
            <p class="text-2xl font-bold text-amber-600 mt-2">Rp {{ number_format($report['solvency_and_qardh']['social_purification_fund_idr'] ?? 0, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Ringkasan Audit Kepatuhan Syariah & Qardh Hasan</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="border border-gray-100 p-4 rounded-lg">
                <h3 class="text-sm font-bold text-gray-700 mb-2">Pemisahan Rekening & Pinjaman Talangan (Qardh Hasan)</h3>
                <p class="text-sm text-gray-600 mb-2">Rekening tabarru' dipisahkan secara independen dari rekening modal pengelola (Operator).</p>
                <div class="flex justify-between items-center text-sm py-1 border-t border-gray-100">
                    <span class="text-gray-500">Status Defisit Tabarru:</span>
                    <span class="font-bold {{ ($report['solvency_and_qardh']['is_in_deficit'] ?? false) ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ ($report['solvency_and_qardh']['is_in_deficit'] ?? false) ? 'DEFISIT (Perlu Qardh)' : 'SURPLUS (Tidak Butuh Qardh)' }}
                    </span>
                </div>
                <div class="flex justify-between items-center text-sm py-1">
                    <span class="text-gray-500">Pinjaman Qardh Operator:</span>
                    <span class="font-mono text-gray-900 font-bold">Rp {{ number_format($report['solvency_and_qardh']['qardh_hasan_loan_idr'] ?? 0, 0, ',', '.') }}</span>
                </div>
            </div>

            <div class="border border-gray-100 p-4 rounded-lg">
                <h3 class="text-sm font-bold text-gray-700 mb-2">Audit Pembagian Surplus Underwriting</h3>
                <p class="text-sm text-gray-600 mb-2">Distribusi surplus dana tabarru' dilakukan secara transparan sesuai formula Fatwa DSN No. 53/DSN-MUI/III/2006.</p>
                <div class="flex justify-between items-center text-sm py-1 border-t border-gray-100">
                    <span class="text-gray-500">Total Surplus Dibagikan:</span>
                    <span class="font-mono text-emerald-600 font-bold">Rp {{ number_format($report['surplus_audit']['total_surplus_distributed_idr'] ?? 0, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center text-sm py-1">
                    <span class="text-gray-500">Hibah Peserta:</span>
                    <span class="font-mono text-gray-900 font-bold">Rp {{ number_format($report['surplus_audit']['total_hibah_to_participants_idr'] ?? 0, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

