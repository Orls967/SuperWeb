<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Loyalty Duta Points & Voucher Mall
                </h2>
                <p class="text-sm text-slate-500 mt-1">Reward program belanja pengunjung Duta Mall, klaim struk unik, dan penukaran voucher</p>
            </div>
            <div class="flex items-center gap-3">
                <form action="{{ route('mall.loyalty.settle') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/20 transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span>Settle Voucher ke Tenant</span>
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            @if($errors->any())
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Saldo Poin Saya</p>
                    <h3 class="text-3xl font-black text-amber-500 mt-2">{{ number_format($userPoints) }} <span class="text-sm font-semibold text-slate-400">PTS</span></h3>
                    <p class="text-xs text-slate-500 mt-1">Dapat ditukarkan dengan voucher</p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Poin Aktif Beredar</p>
                    <h3 class="text-2xl font-black text-indigo-600 mt-2">{{ number_format($overview['total_points_active']) }} PTS</h3>
                    <p class="text-xs text-slate-500 mt-1">Liabilitas buku besar mall</p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Anggota Loyalty Terdaftar</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-2">{{ number_format($overview['member_counts']['total']) }} Orang</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Silver: {{ $overview['member_counts']['silver'] }} | Gold: {{ $overview['member_counts']['gold'] }} | Plat: {{ $overview['member_counts']['platinum'] }}
                    </p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Voucher Terselesaikan (Settled)</p>
                    <h3 class="text-2xl font-black text-emerald-600 mt-2">Rp {{ number_format($overview['voucher_stats']['settled_amount'], 0, ',', '.') }}</h3>
                    <p class="text-xs text-slate-500 mt-1">{{ $overview['voucher_stats']['used_vouchers'] }} voucher digunakan</p>
                </div>
            </div>

            <!-- Two Columns: Klaim Struk & Redeem Voucher -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Card Klaim Struk Belanja -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-slate-800 text-lg">Klaim Struk Belanja Tenant</h3>
                            <p class="text-xs text-slate-500">Dapatkan 1 Duta Point (PTS) setiap pembelanjaan Rp 10.000 (dikali tier)</p>
                        </div>
                    </div>

                    <form action="{{ route('mall.loyalty.claim') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Pilih Tenant / Toko</label>
                            <select name="tenant_id" class="w-full text-sm rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">-- Tenant Umum Duta Mall --</option>
                                @foreach($tenants as $t)
                                    <option value="{{ $t->id }}">{{ $t->brand_name }} ({{ $t->category }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nomor Struk Unik *</label>
                                <input type="text" name="receipt_number" placeholder="Misal: STR-2026-9901" required class="w-full text-sm rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tanggal Transaksi *</label>
                                <input type="date" name="receipt_date" value="{{ date('Y-m-d') }}" required class="w-full text-sm rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Total Nominal Belanja (Rp) *</label>
                            <input type="number" name="receipt_amount" placeholder="Misal: 150000" min="1000" step="1000" required class="w-full text-sm rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-sm font-bold shadow-md shadow-blue-500/20 transition-all flex items-center justify-center gap-2">
                            <span>Klaim & Tambah Duta Points</span>
                        </button>
                    </form>
                </div>

                <!-- Card Redeem Voucher -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2.5 bg-amber-50 text-amber-600 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-slate-800 text-lg">Katalog Voucher Belanja</h3>
                            <p class="text-xs text-slate-500">Tukarkan Duta Points dengan potongan diskon belanja di Duta Mall</p>
                        </div>
                    </div>

                    <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                        @forelse($overview['templates'] as $tmpl)
                            <div class="p-4 rounded-xl border border-slate-200 hover:border-amber-400 transition-all flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="font-bold text-slate-800 text-sm">{{ $tmpl->title }}</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Nilai Diskon: <span class="font-bold text-emerald-600">Rp {{ number_format($tmpl->nominal_value, 0, ',', '.') }}</span></p>
                                    <p class="text-xs text-slate-400">Min. belanja: Rp {{ number_format($tmpl->min_spend, 0, ',', '.') }} | Berlaku {{ $tmpl->validity_days }} hari</p>
                                </div>
                                <form action="{{ route('mall.loyalty.redeem', $tmpl) }}" method="POST">
                                    @csrf
                                    <button type="submit" @if($userPoints < $tmpl->points_required) disabled @endif class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all {{ $userPoints >= $tmpl->points_required ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-md shadow-amber-500/20' : 'bg-slate-100 text-slate-400 cursor-not-allowed' }}">
                                        Tukar {{ $tmpl->points_required }} PTS
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400 py-6 text-center">Belum ada template voucher aktif.</p>
                        @endforelse
                    </div>

                    <!-- Gunakan Voucher di Kasir Tenant -->
                    <div class="mt-6 pt-6 border-t border-slate-100">
                        <h4 class="font-bold text-slate-800 text-sm mb-2">Validasi Pemakaian Voucher (Kasir Tenant)</h4>
                        <form action="{{ route('mall.loyalty.use') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            @csrf
                            <input type="text" name="voucher_code" placeholder="Kode Voucher (VCH-...)" required class="text-xs rounded-xl border-slate-200 uppercase">
                            <select name="tenant_id" required class="text-xs rounded-xl border-slate-200">
                                <option value="">-- Pilih Tenant --</option>
                                @foreach($tenants as $t)
                                    <option value="{{ $t->id }}">{{ $t->brand_name }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="transaction_amount" placeholder="Total Belanja (Rp)" min="1" required class="text-xs rounded-xl border-slate-200">
                            <div class="sm:col-span-3">
                                <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition-all">
                                    Gunakan Voucher
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tabel Riwayat Klaim Struk Terbaru -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100">
                    <h3 class="font-extrabold text-slate-800 text-lg">Riwayat Klaim Struk Terbaru</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Daftar transaksi struk belanja pengunjung yang telah divalidasi</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-400 border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4">Nomor Struk</th>
                                <th class="px-6 py-4">Pengguna</th>
                                <th class="px-6 py-4">Tenant</th>
                                <th class="px-6 py-4">Tanggal Transaksi</th>
                                <th class="px-6 py-4 text-right">Nomor Belanja</th>
                                <th class="px-6 py-4 text-right">Poin Didapat</th>
                                <th class="px-6 py-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($overview['recent_claims'] as $c)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4 font-mono font-bold text-slate-800">{{ $c->receipt_number }}</td>
                                    <td class="px-6 py-4">{{ $c->user?->name ?? 'Anonim' }}</td>
                                    <td class="px-6 py-4 font-medium">{{ $c->tenant?->brand_name ?? 'Tenant Umum' }}</td>
                                    <td class="px-6 py-4 text-xs text-slate-500">{{ $c->receipt_date->format('d M Y') }}</td>
                                    <td class="px-6 py-4 text-right font-medium text-slate-800">Rp {{ number_format($c->receipt_amount, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right font-bold text-amber-500">+{{ $c->points_earned }} PTS</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-50 text-emerald-700">
                                            {{ $c->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-slate-400">Belum ada riwayat klaim struk belanja.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
