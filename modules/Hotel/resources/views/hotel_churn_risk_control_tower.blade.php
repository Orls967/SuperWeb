@extends('layouts.app')

@section('title', 'Menara Kontrol Retensi Tamu & Churn Risk — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-rose-50 text-rose-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Menara Kontrol Retensi Tamu & Churn Risk (H9)</h1>
                    <p class="text-sm text-slate-500">Prediksi Atrisi Tamu Cerdas Berbasis Matriks RFM, Analisis Keluhan Pelayanan & Protokol Win-Back Otomatis</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 animate-pulse">
                    ● Real-Time AI Churn Radar Active
                </span>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Profil Tamu Dipantau</span>
                <p class="text-2xl font-bold text-slate-900 mt-1">12.450 Tamu</p>
                <span class="text-xs text-slate-400 mt-1 block">Database CRM Loyalitas Terintegrasi</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Tamu Kategori Risiko Tinggi (> 0.65)</span>
                <p class="text-2xl font-bold text-rose-600 mt-1">48 Tamu</p>
                <span class="text-xs text-rose-500 mt-1 block">Wajib intervensi langsung GM</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Tingkat Keberhasilan Win-Back</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">74.2%</p>
                <span class="text-xs text-emerald-500 mt-1 block">Re-booking dalam 30 hari pasca tawaran</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Rata-rata Customer Lifetime Value</span>
                <p class="text-2xl font-bold text-indigo-600 mt-1">IDR 34.800.000</p>
                <span class="text-xs text-indigo-500 mt-1 block">Pendapatan per tamu aktif 3 tahun</span>
            </div>
        </div>

        <!-- Risk Matrix Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Antrean Intervensi Tamu Berisiko Tinggi</h2>
                    <p class="text-xs text-slate-500">Tamu dengan skor atrisi tinggi yang memerlukan perhatian dan penawaran pemulihan segera</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-3">Nama Tamu & ID</th>
                            <th class="px-6 py-3">Hari Sejak Inap Terakhir</th>
                            <th class="px-6 py-3">Total Menginap (1 Thn)</th>
                            <th class="px-6 py-3">Keluhan Terbuka</th>
                            <th class="px-6 py-3">Rating Kepuasan</th>
                            <th class="px-6 py-3 text-center">Skor Churn (H9)</th>
                            <th class="px-6 py-3">Rencana Intervensi</th>
                            <th class="px-6 py-3 text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-900 block">Bpk. Hendra Gunawan</span>
                                <span class="text-xs font-mono text-slate-400">GST-98421 • Gold Member</span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-rose-600 font-bold">210 Hari Lalu</td>
                            <td class="px-6 py-4 font-mono text-xs">1 Kali</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800">2 Kasus</span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-amber-600">★ 2.0 / 5.0</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 bg-rose-100 text-rose-800 rounded-full font-mono font-bold text-xs">
                                    0.7850 (HIGH)
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs font-medium text-slate-700">
                                Direct GM Outreach + Complimentary Weekend Stay Voucher
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                                    Kirim Win-Back
                                </button>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-900 block">Ibu Ratna Dewi</span>
                                <span class="text-xs font-mono text-slate-400">GST-44109 • Silver Member</span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-amber-600 font-bold">120 Hari Lalu</td>
                            <td class="px-6 py-4 font-mono text-xs">2 Kali</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-700">0 Kasus</span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-amber-600">★ 3.5 / 5.0</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 bg-amber-100 text-amber-800 rounded-full font-mono font-bold text-xs">
                                    0.4200 (MEDIUM)
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs font-medium text-slate-700">
                                Diskon 20% Kamar Menginap Berikutnya + Double Points
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                                    Kirim Diskon
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Wellness & Spa Recovery Roster Component -->
        @include('hotel::components.spa_therapist_roster_calendar')
    </div>
</div>
@endsection
