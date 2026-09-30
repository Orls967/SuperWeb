<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Portal Mandiri Tenant — {{ $tenant->brand_name }}
                </h2>
                <p class="text-sm text-slate-500 mt-1">{{ $tenant->company_name }} &bull; Kategori: {{ $tenant->category->label() }}</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('mall.portal.sales') }}" class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <span>Lapor Omzet Bulanan</span>
                </a>
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

            @if(session('error'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-center justify-between">
                    <span>{{ session('error') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-rose-500">&times;</button>
                </div>
            @endif

            <!-- Quick Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Wallet Balance Card -->
                <div class="p-6 bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-2xl shadow-md">
                    <span class="text-xs uppercase font-bold text-indigo-300 tracking-wider">Saldo Dompet Tenant</span>
                    <h3 class="text-3xl font-black mt-2">Rp {{ number_format($walletBalance, 0, ',', '.') }}</h3>
                    <p class="text-xs text-indigo-200/70 mt-1">Digunakan untuk auto-debit & pelunasan invoice</p>
                    <div class="mt-4 pt-4 border-t border-indigo-900/60 flex items-center justify-between">
                        <span class="text-xs text-indigo-300">ID Pengguna: #{{ auth()->id() }}</span>
                        <a href="{{ route('wallet.index') }}" class="text-xs font-bold text-amber-400 hover:text-amber-300">Isi Saldo &rarr;</a>
                    </div>
                </div>

                <!-- Outstanding Invoices Card -->
                <div class="p-6 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <span class="text-xs uppercase font-bold text-slate-400 tracking-wider">Total Tagihan Tertunggak</span>
                    <h3 class="text-3xl font-black text-rose-600 mt-2">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</h3>
                    <p class="text-xs text-slate-500 mt-1">Harap segera dilunasi sebelum jatuh tempo</p>
                    <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span>Denda: 0.1%/hari jika lewat tempo</span>
                    </div>
                </div>

                <!-- Unit & Lease Card -->
                <div class="p-6 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <span class="text-xs uppercase font-bold text-slate-400 tracking-wider">Unit Usaha Aktif</span>
                    @forelse($activeLeases as $l)
                        <div class="mt-2 flex items-center justify-between">
                            <div>
                                <span class="text-xl font-black text-slate-800">Unit {{ $l->unit?->unit_number }}</span>
                                <div class="text-xs text-slate-400">Lt. {{ $l->unit?->floor }} &bull; {{ $l->unit?->area_sqm }} m²</div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold border {{ $l->status->badgeClass() }}">
                                {{ $l->status->label() }}
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 mt-2">Belum ada kontrak lease aktif.</p>
                    @endforelse
                </div>
            </div>

            <!-- Invoices List & Overtime Form -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Invoices Table (2 cols) -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-bold text-slate-800 text-sm">Riwayat Tagihan Bulanan</h3>
                        <span class="text-xs text-slate-400">10 Tagihan Terakhir</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50/80 text-slate-500 uppercase font-semibold text-[11px] border-b border-slate-100">
                                    <th class="py-3 px-4">No. Invoice</th>
                                    <th class="py-3 px-4">Periode</th>
                                    <th class="py-3 px-4 text-right">Total</th>
                                    <th class="py-3 px-4 text-right">Sisa Bayar</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @forelse($invoices as $inv)
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="py-3 px-4 font-mono font-bold text-blue-600">
                                            {{ $inv->invoice_number }}
                                        </td>
                                        <td class="py-3 px-4 font-medium">{{ $inv->period_month }}</td>
                                        <td class="py-3 px-4 text-right font-bold text-slate-800">Rp {{ number_format($inv->total_amount, 0, ',', '.') }}</td>
                                        <td class="py-3 px-4 text-right font-black {{ $inv->remainingAmount() > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                            Rp {{ number_format($inv->remainingAmount(), 0, ',', '.') }}
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $inv->status->badgeClass() }}">
                                                {{ $inv->status->label() }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <a href="{{ route('mall.portal.invoice', $inv->id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 {{ $inv->remainingAmount() > 0 ? 'bg-indigo-600 hover:bg-indigo-700 text-white font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }} rounded-lg text-xs transition">
                                                <span>{{ $inv->remainingAmount() > 0 ? 'Bayar Tagihan' : 'Lihat Kwitansi' }}</span>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-slate-400">
                                            Belum ada data tagihan untuk tenant Anda.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Overtime AC Request Form (1 col) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <div>
                        <h4 class="font-bold text-slate-800 text-sm">Pengajuan Lembur AC (Overtime)</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Operasional tambahan pendingin udara di luar jam mall (Rp 250.000/jam)</p>
                    </div>

                    <form action="{{ route('mall.portal.overtime') }}" method="POST" class="space-y-3">
                        @csrf
                        @if($activeLeases->isNotEmpty())
                            <input type="hidden" name="lease_id" value="{{ $activeLeases->first()->id }}">
                        @endif

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Tanggal Lembur</label>
                            <input type="date" name="date" value="{{ date('Y-m-d') }}" min="{{ date('Y-m-d') }}" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2" required>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Mulai</label>
                                <input type="time" name="start_time" value="22:00" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Selesai</label>
                                <input type="time" name="end_time" value="24:00" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Total Durasi (Jam)</label>
                            <input type="number" step="0.5" min="0.5" max="12" name="hours" value="2" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2" required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Keperluan / Keterangan</label>
                            <textarea name="reason" rows="2" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2" placeholder="Contoh: Stock opname bulanan atau dekorasi display" required></textarea>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition">
                            Kirim Pengajuan Lembur
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
