@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Pemantau Kredensial Pintu & Smart Lock IoT (S1)</h1>
            <p class="text-sm text-slate-500">Telemetri turnstile lift tamu, status baterai kunci digital pintu kamar, dan audit akses anti-passback.</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Pindai Gateway IoT Seluruh Lantai
            </button>
        </div>
    </div>

    <!-- Metrik Perangkat Kunci Digital -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Kunci Pintu Online</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">98 / 100 Unit</div>
            <p class="text-xs text-slate-500 mt-1">Gateway Zigbee/Bluetooth Aktif</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Akses Pintu Sukses (24 Jam)</span>
            <div class="mt-2 text-2xl font-bold text-sky-600">1.428 Kali</div>
            <p class="text-xs text-slate-500 mt-1">Verifikasi token QR & RFID</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Peringatan Baterai Lemah</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">2 Unit (< 20%)</div>
            <p class="text-xs text-slate-500 mt-1">Kamar 308 & Kamar 412</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Akses Ditolak (Security Alert)</span>
            <div class="mt-2 text-2xl font-bold text-rose-600">3 Percobaan</div>
            <p class="text-xs text-slate-500 mt-1">Token kedaluwarsa pasca checkout</p>
        </div>
    </div>

    <!-- Log Aktivitas Pemindaian Kunci Pintu Terbaru -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 font-bold text-slate-900">
            Log Real-Time Pemindaian Kunci Kamar & Turnstile (Audit Trail)
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Waktu Log</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Lokasi Perangkat</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Metode Kredensial</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Identitas Tamu / Kunci</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status Akses</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white font-mono text-xs">
                <tr>
                    <td class="px-6 py-4 text-slate-600">2026-11-08 04:12:15</td>
                    <td class="px-6 py-4 font-sans font-semibold text-slate-800">Pintu Kamar 304</td>
                    <td class="px-6 py-4 font-sans text-slate-600">QR Smart Key (Mobile)</td>
                    <td class="px-6 py-4 text-slate-700">SES-91045-304</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 font-sans font-bold">GRANTED</span>
                    </td>
                    <td class="px-6 py-4 font-sans text-slate-500">Pintu terbuka normal</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 text-slate-600">2026-11-08 04:08:44</td>
                    <td class="px-6 py-4 font-sans font-semibold text-slate-800">Lift Tamu Lantai 5</td>
                    <td class="px-6 py-4 font-sans text-slate-600">RFID Keycard (Fisik)</td>
                    <td class="px-6 py-4 text-slate-700">CRD-RFID-8812</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 font-sans font-bold">GRANTED</span>
                    </td>
                    <td class="px-6 py-4 font-sans text-slate-500">Akses lantai penthouse diizinkan</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 text-slate-600">2026-11-08 03:45:02</td>
                    <td class="px-6 py-4 font-sans font-semibold text-slate-800">Pintu Kamar 108</td>
                    <td class="px-6 py-4 font-sans text-slate-600">QR Smart Key (Mobile)</td>
                    <td class="px-6 py-4 text-slate-700">SES-90871-108</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-rose-100 text-rose-800 px-2 py-0.5 font-sans font-bold">DENIED</span>
                    </td>
                    <td class="px-6 py-4 font-sans text-rose-600">Token kedaluwarsa (checkout telah diproses)</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
