@extends('layouts.app')

@section('title', 'Papan Penjadwalan Janji Temu Spa & Fasilitas Kebugaran — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-teal-50 text-teal-700 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Papan Penjadwalan Janji Temu Spa & Fasilitas Kebugaran</h1>
                    <p class="text-sm text-slate-500">Alokasi Terapis Berlisensi, Manajemen Paviliun Perawatan, Dinamika Tarif & Pembebanan Folio Kamar</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3.5 py-1.5 rounded-full text-xs font-semibold bg-teal-50 text-teal-800 border border-teal-200">
                    Kapasitas Paviliun: 6/8 Terisi
                </span>
                <button class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                    + Jadwalkan Sesi Perawatan Baru
                </button>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Reservasi Hari Ini</span>
                <p class="text-2xl font-bold text-slate-900 mt-1">18 Sesi</p>
                <span class="text-xs text-slate-400 mt-1 block">8 Sesi Selesai, 4 Berjalan, 6 Menunggu</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Tingkat Utilisasi Terapis</span>
                <p class="text-2xl font-bold text-teal-600 mt-1">87.5%</p>
                <span class="text-xs text-teal-500 mt-1 block">7 dari 8 Terapis Aktif Bertugas</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Pendapatan Spa Kotor (TrevPAR)</span>
                <p class="text-2xl font-bold text-indigo-600 mt-1">IDR 12.850.000</p>
                <span class="text-xs text-indigo-500 mt-1 block">Rata-rata IDR 713.800 per Sesi</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Biaya Bahan Baku Habis Pakai (BOM)</span>
                <p class="text-2xl font-bold text-slate-800 mt-1">IDR 1.840.000</p>
                <span class="text-xs text-slate-400 mt-1 block">Margin Kotor Layanan Spa: 85.6%</span>
            </div>
        </div>

        <!-- Interactive Treatment Schedule Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Agenda Sesi Perawatan Relaksasi Hari Berjalan</h2>
                    <p class="text-xs text-slate-500">Integrasi pembebanan langsung ke nomor kamar tamu dengan validasi tanda tangan digital</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-slate-400">Filter Paviliun:</span>
                    <select class="text-xs border border-slate-200 rounded-lg px-2 py-1 bg-slate-50 text-slate-700">
                        <option value="ALL">Semua Paviliun & Cabana</option>
                        <option value="PAVILION">Single Pavilions</option>
                        <option value="ROYAL">Royal Sanctuary Suite</option>
                        <option value="CABANA">Poolside Cabana</option>
                    </select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-3">Waktu Sesi</th>
                            <th class="px-6 py-3">Nama Tamu & Kamar</th>
                            <th class="px-6 py-3">Jenis Treatment</th>
                            <th class="px-6 py-3">Terapis Bertugas</th>
                            <th class="px-6 py-3">Ruang / Paviliun</th>
                            <th class="px-6 py-3 text-right">Tarif Layanan</th>
                            <th class="px-6 py-3 text-center">Status Pembebanan</th>
                            <th class="px-6 py-3 text-center">Aksi Front Office</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 font-mono text-xs font-bold text-slate-800">10:00 - 11:30</td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-900 block">Ibu Jessica Tan</span>
                                <span class="text-xs font-mono text-indigo-600">Suite 302 • Platinum VIP</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-800 block">Royal Herbal Aromatherapy</span>
                                <span class="text-xs text-slate-400">Durasi: 90 Menit</span>
                            </td>
                            <td class="px-6 py-4 text-xs font-medium text-slate-700">Ni Luh Putu (Senior)</td>
                            <td class="px-6 py-4 text-xs text-slate-600">Pavilion Melati 01</td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">IDR 650.000</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">
                                    POSTED KE FOLIO
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-lg transition">
                                    Lihat Slip
                                </button>
                            </td>
                        </tr>

                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 font-mono text-xs font-bold text-teal-600">14:00 - 15:00</td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-900 block">Bpk. Ronald Simatupang</span>
                                <span class="text-xs font-mono text-indigo-600">Villa 08 • Corporate Direct</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-800 block">Balinese Deep Tissue Massage</span>
                                <span class="text-xs text-slate-400">Durasi: 60 Menit</span>
                            </td>
                            <td class="px-6 py-4 text-xs font-medium text-slate-700">Wayan Sari</td>
                            <td class="px-6 py-4 text-xs text-slate-600">Royal Sanctuary B</td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">IDR 450.000</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                    IN TREATMENT
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button class="px-3 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-700 text-xs font-medium rounded-lg transition">
                                    Selesaikan
                                </button>
                            </td>
                        </tr>

                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 font-mono text-xs font-bold text-slate-500">16:30 - 18:00</td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-900 block">Mr. Hans Becker</span>
                                <span class="text-xs font-mono text-slate-500">Deluxe 412 • Walk-in</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-800 block">Volcanic Hot Stone Therapy</span>
                                <span class="text-xs text-amber-600 font-medium">Peak Hour (+20% Surge)</span>
                            </td>
                            <td class="px-6 py-4 text-xs font-medium text-slate-700">Made Artha</td>
                            <td class="px-6 py-4 text-xs text-slate-600">Pavilion Cempaka 02</td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">IDR 1.020.000</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">
                                    SCHEDULED
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-lg transition">
                                    Edit Slot
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Therapist Roster Component -->
        @include('hotel::components.spa_therapist_roster_calendar')

        <!-- Guest Loyalty Passport QR Component -->
        @include('hotel::components.hotel_loyalty_passport_qr_card')
    </div>
</div>
@endsection
