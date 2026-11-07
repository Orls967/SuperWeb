<x-hotel::hotel_layout title="Portal Donasi Kredit Karbon ESG - SuperWeb Hospitality">
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Portal Donasi Kredit Karbon & Pelestarian Lingkungan (ESG)</h1>
                <p class="text-sm text-slate-500 mt-1">Konversikan poin loyalitas Travel Pass menjadi aksi nyata penghijauan hutan mangrove dan pengurangan jejak karbon tersertifikasi VCS.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></span> Gold Standard & VCS Verified
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Formula Penyerapan Karbon</p>
                <h3 class="text-2xl font-bold text-emerald-600 mt-2">1 PTS = 2.5 kg CO2</h3>
                <p class="text-xs text-slate-500 mt-1">Valuasi Rp 100 per point (Rp 40/kg CO2)</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Lokasi Konservasi Terdaftar</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-2">Tahura Ngurah Rai</h3>
                <p class="text-xs text-slate-500 mt-1">Denpasar & Badung, Bali - Indonesia</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Sertifikat Digital</p>
                <h3 class="text-2xl font-bold text-indigo-600 mt-2">Nomor Seri VCS Unik</h3>
                <p class="text-xs text-slate-500 mt-1">Audit trail langsung ke Ledger Akuntansi</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Formulir Donasi Poin Hijau</h2>
                <form action="{{ route('hotel.esg.donate') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Pilih Anggota Travel Pass</label>
                        <select name="travel_pass_id" required class="w-full text-sm border-slate-300 rounded-lg shadow-sm focus:ring-emerald-500 focus:border-emerald-500">
                            @foreach($passes as $p)
                                <option value="{{ $p->id }}">{{ $p->pass_number }} ({{ $p->tier }}) - {{ number_format($p->pts_balance) }} PTS</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Program Konservasi ESG</label>
                        <select name="esg_program_code" required class="w-full text-sm border-slate-300 rounded-lg shadow-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="MANGROVE_REFORESTATION_BALI">Restorasi Hutan Mangrove Pesisir Bali</option>
                            <option value="CORAL_REEF_RESTORATION_KOMODO">Adopsi Terumbu Karang Labuan Bajo</option>
                            <option value="SOLAR_PANEL_COMMUNITY_SUMBA">Elektrifikasi Surya Komunitas Sumba</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Jumlah Poin Didonasikan (PTS)</label>
                        <input type="number" name="points_to_donate" value="1000" min="100" step="100" required class="w-full text-sm border-slate-300 rounded-lg shadow-sm focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="text-xs text-slate-500 mt-1">1.000 PTS = Menanam 10 bibit mangrove & menyerap 2.500 kg CO2.</p>
                    </div>

                    <button type="submit" class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm rounded-lg shadow-sm transition">
                        Tukarkan Poin & Terbitkan Sertifikat
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Dampak Kolektif Pelestarian Ekosistem</h2>
                <div class="space-y-4">
                    <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-200 flex items-start gap-4">
                        <div class="p-3 bg-emerald-100 rounded-lg text-emerald-700 font-bold text-xl">🌱</div>
                        <div>
                            <h4 class="text-sm font-bold text-emerald-900">Restorasi Karbon Biru Mangrove</h4>
                            <p class="text-xs text-emerald-700 mt-1">Setiap kontribusi poin diakumulasikan ke dalam akun kliring eksternal hotel untuk pembelian bibit tanaman dan perawatan berkala oleh komunitas nelayan lokal Bali.</p>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg bg-blue-50 border border-blue-200 flex items-start gap-4">
                        <div class="p-3 bg-blue-100 rounded-lg text-blue-700 font-bold text-xl">🌊</div>
                        <div>
                            <h4 class="text-sm font-bold text-blue-900">Transparansi Ledger Keuangan</h4>
                            <p class="text-xs text-blue-700 mt-1">Poin yang didonasikan otomatis memicu posting ledger idempoten: mendebit Liabilitas Poin Loyalitas dan mengkredit Rekening Donasi Kliring Eksternal tanpa perantara.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-hotel::hotel_layout>

