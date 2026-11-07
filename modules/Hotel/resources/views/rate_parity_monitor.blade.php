@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Pemantau Paritas Tarif & Saluran OTA</h1>
            <p class="text-sm text-slate-500">Audit perbandingan tarif langsung vs Online Travel Agents (Traveloka, Booking.com, Agoda).</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Pindai Saluran OTA Sekarang
            </button>
        </div>
    </div>

    <!-- Ringkasan Kepatuhan Paritas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Skor Paritas Global</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">98.4%</div>
            <p class="text-xs text-slate-500 mt-1">Dalam batas aman deviasi < 2%</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Pelanggaran OTA Terdeteksi</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">2 Saluran</div>
            <p class="text-xs text-slate-500 mt-1">Traveloka undercutting (-5%)</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Kekebalan Kontrak B2B</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">14 Akun</div>
            <p class="text-xs text-slate-500 mt-1">Kebal fluktuasi BAR harian</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Rekomendasi Penyesuaian</span>
            <div class="mt-2 text-2xl font-bold text-slate-800">Auto-Match</div>
            <p class="text-xs text-slate-500 mt-1">Sinkronisasi rate calendar siap</p>
        </div>
    </div>

    <!-- Matriks Perbandingan Kanal OTA -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 font-bold text-slate-900">
            Perbandingan Tarif Harian per Saluran Distribusi (Deluxe Room — Malam Ini)
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Saluran Jual</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Tipe Penjualan</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Tarif Tayang (Rp)</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Deviasi (%)</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status Paritas</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Aksi Mitigasi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <tr>
                    <td class="px-6 py-4 font-semibold text-slate-900">Direct Brand Web / App</td>
                    <td class="px-6 py-4 text-slate-600">Direct Official</td>
                    <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">1.250.000</td>
                    <td class="px-6 py-4 text-right font-mono text-slate-400">0.0% (Acuan)</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2.5 py-1 text-xs font-semibold">BENCHMARK</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-xs text-slate-400">Tarif Resmi</span>
                    </td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-semibold text-slate-900">Traveloka OTA</td>
                    <td class="px-6 py-4 text-slate-600">Merchant Model</td>
                    <td class="px-6 py-4 text-right font-mono font-medium text-amber-600">1.187.500</td>
                    <td class="px-6 py-4 text-right font-mono text-amber-600">-5.0%</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-amber-100 text-amber-800 px-2.5 py-1 text-xs font-semibold">UNDERCUTTING</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Kirim Teguran Paritas</button>
                    </td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-semibold text-slate-900">Booking.com</td>
                    <td class="px-6 py-4 text-slate-600">Agency Model</td>
                    <td class="px-6 py-4 text-right font-mono font-medium text-slate-900">1.250.000</td>
                    <td class="px-6 py-4 text-right font-mono text-slate-400">0.0%</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2.5 py-1 text-xs font-semibold">IN_PARITY</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-xs text-slate-400">Sesuai Kontrak</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
