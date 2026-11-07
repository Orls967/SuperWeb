<x-hotel::hotel_layout title="Pusat Manajemen Kartu Co-Brand Perbankan - SuperWeb Hospitality">
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Pusat Integrasi Kartu Kredit Co-Brand & Cashback Loyalitas</h1>
                <p class="text-sm text-slate-500 mt-1">Penerbitan rebate poin otomatis, co-funding subsidi bank 60/40, dan sinkronisasi statement transaksi perbankan.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></span> Gateway Co-Brand Aktif
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Mitra Bank Terkoneksi</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-2">4 Bank Utama</h3>
                <p class="text-xs text-slate-500 mt-1">BCA, Mandiri, BNI, CIMB Niaga</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Skema Co-Funding Subsidi</p>
                <h3 class="text-2xl font-bold text-indigo-600 mt-2">60% Bank / 40% Hotel</h3>
                <p class="text-xs text-slate-500 mt-1">Sesuai Perjanjian Kerja Sama IFRS 15</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Batas Maksimal Rebate Harian</p>
                <h3 class="text-2xl font-bold text-emerald-600 mt-2">50.000 PTS / Kartu</h3>
                <p class="text-xs text-slate-500 mt-1">Dilengkapi AI Fraud Velocity Shield</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Simulasi & Proses Transaksi Kartu</h2>
                <form action="{{ route('hotel.co_brand.cashback') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Pilih Anggota Travel Pass</label>
                        <select name="travel_pass_id" required class="w-full text-sm border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach($passes as $p)
                                <option value="{{ $p->id }}">{{ $p->pass_number }} ({{ $p->tier }}) - {{ number_format($p->pts_balance) }} PTS</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Bank Penerbit</label>
                        <select name="bank_code" required class="w-full text-sm border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="BCA">BCA SuperWeb Infinite</option>
                            <option value="MANDIRI">Mandiri Signature Hospitality</option>
                            <option value="BNI">BNI Platinum Travel Rewards</option>
                            <option value="CIMB">CIMB Niaga World ALL-Accor</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Tingkat Kartu Kredit</label>
                        <select name="card_tier" required class="w-full text-sm border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="BLACK">Black / Infinite (5% Rebate)</option>
                            <option value="PLATINUM">Platinum / Signature (3% Rebate)</option>
                            <option value="GOLD">Gold (1.5% Rebate)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Nilai Belanja Gesek Kartu (IDR)</label>
                        <input type="number" name="spend_amount_idr" value="5000000" min="10000" step="10000" required class="w-full text-sm border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <button type="submit" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-lg shadow-sm transition">
                        Proses Cashback & Akrual Poin
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Daftar Anggota Co-Brand Card Holder Aktif</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-semibold border-b">
                            <tr>
                                <th class="py-3 px-4">Nomor Pass</th>
                                <th class="py-3 px-4">Tingkat Tier</th>
                                <th class="py-3 px-4">Saldo Poin</th>
                                <th class="py-3 px-4">Status Akun</th>
                                <th class="py-3 px-4">Benefit Gesek</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($passes as $pass)
                                <tr>
                                    <td class="py-3 px-4 font-mono font-medium text-slate-900">{{ $pass->pass_number }}</td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold
                                            {{ $pass->tier === 'BLACK' ? 'bg-slate-900 text-amber-300' : ($pass->tier === 'PLATINUM' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800') }}">
                                            {{ $pass->tier }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-slate-900">{{ number_format($pass->pts_balance) }} PTS</td>
                                    <td class="py-3 px-4">
                                        <span class="text-xs text-emerald-600 font-semibold">Aktif</span>
                                    </td>
                                    <td class="py-3 px-4 text-xs text-slate-500">Rebate + Akses Lounge</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-hotel::hotel_layout>

