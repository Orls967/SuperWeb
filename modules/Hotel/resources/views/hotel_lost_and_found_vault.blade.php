@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Buku Ekspedisi Barang Tertinggal (Lost & Found Vault)</h1>
            <p class="text-sm text-slate-500">Pencatatan barang berharga tamu tertinggal di kamar atau area publik hotel sesuai SOP tata graha.</p>
        </div>
        <div>
            <button type="button" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Daftarkan Barang Temuan Baru
            </button>
        </div>
    </div>

    <!-- Tabel Daftar Barang Tertinggal -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Kode Tracking</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Lokasi Penemuan</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Deskripsi Barang</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Petugas Penemu</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Loker Penyimpanan</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status Pengambilan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <tr>
                    <td class="px-6 py-4 font-mono font-medium text-slate-900">LNF-202611-8841</td>
                    <td class="px-6 py-4 text-slate-700">Kamar 302 (Meja Kerja)</td>
                    <td class="px-6 py-4 font-semibold text-slate-900">Jam Tangan Seiko Automatic Stainless Steel</td>
                    <td class="px-6 py-4 text-slate-600">Dewi Sartika (Housekeeping)</td>
                    <td class="px-6 py-4 text-center font-mono text-slate-800">VAULT_LOCKER_B1</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-sky-100 text-sky-800 px-2 py-0.5 text-xs font-semibold">TERSIMPAN AMAN</span>
                    </td>
                </tr>
                <tr>
                    <td class="px-6 py-4 font-mono font-medium text-slate-900">LNF-202611-8839</td>
                    <td class="px-6 py-4 text-slate-700">Lobby Lounge Sofa A</td>
                    <td class="px-6 py-4 font-semibold text-slate-900">Paspor & Dompet Kulit Cokelat</td>
                    <td class="px-6 py-4 text-slate-600">Ahmad Fauzi (Front Office)</td>
                    <td class="px-6 py-4 text-center font-mono text-slate-800">VAULT_LOCKER_A1</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-semibold">TELAH DIAMBIL TAMU</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
