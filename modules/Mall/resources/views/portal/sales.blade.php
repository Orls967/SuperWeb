<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('mall.portal.index') }}" class="p-2 bg-white border border-slate-200 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Laporan Omzet Bulanan Tenant
                </h2>
                <p class="text-sm text-slate-500 mt-1">{{ $tenant->brand_name }} &bull; Digunakan untuk perhitungan skema sewa bagi hasil (Revenue Share)</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Sales Report Form (1 col) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <div>
                        <h4 class="font-bold text-slate-800 text-sm">Form Lapor Omzet Penjualan</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Input realisasi omzet bulanan untuk diverifikasi pengelola mall</p>
                    </div>

                    <form action="{{ route('mall.portal.sales.store') }}" method="POST" class="space-y-3">
                        @csrf

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Unit Sewa</label>
                            <select name="lease_id" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2" required>
                                @foreach($leases as $lease)
                                    <option value="{{ $lease->id }}">
                                        Unit {{ $lease->unit?->unit_number }} (Model: {{ $lease->rent_model->label() }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Periode Bulan</label>
                            <input type="month" name="period_month" value="{{ date('Y-m') }}" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2" required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Omzet Kotor (Gross Sales)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-xs font-bold text-slate-400">Rp</span>
                                <input type="number" name="gross_sales" min="0" placeholder="0" class="w-full pl-9 text-xs rounded-xl border-slate-200 py-2 font-mono font-bold text-slate-800" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Omzet Bersih (Net Sales)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-xs font-bold text-slate-400">Rp</span>
                                <input type="number" name="net_sales" min="0" placeholder="0" class="w-full pl-9 text-xs rounded-xl border-slate-200 py-2 font-mono font-bold text-slate-800" required>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1">Omzet setelah dikurangi diskon & promo</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Jumlah Transaksi / Struk</label>
                            <input type="number" name="transaction_count" min="0" placeholder="0" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2 font-mono" required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Tambahan (Opsional)</label>
                            <textarea name="notes" rows="2" class="w-full text-xs rounded-xl border-slate-200 px-3 py-2" placeholder="Keterangan jika ada event promosi / penyesuaian"></textarea>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition">
                            Kirim Laporan Omzet
                        </button>
                    </form>
                </div>

                <!-- Historical Reports Table (2 cols) -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <h4 class="font-bold text-slate-800 text-sm">Riwayat Laporan Omzet</h4>
                        <span class="text-xs text-slate-400">Status sinkronisasi & verifikasi</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 uppercase font-semibold text-[11px] border-b border-slate-100">
                                    <th class="py-3 px-4">Periode</th>
                                    <th class="py-3 px-4">Unit</th>
                                    <th class="py-3 px-4 text-right">Omzet Bersih</th>
                                    <th class="py-3 px-4 text-center">Transaksi</th>
                                    <th class="py-3 px-4 text-center">Sumber Data</th>
                                    <th class="py-3 px-4">Waktu Lapor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @forelse($reports as $r)
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="py-3.5 px-4 font-mono font-bold text-slate-800">{{ $r->period_month }}</td>
                                        <td class="py-3.5 px-4 font-medium">Unit {{ $r->lease?->unit?->unit_number ?? '-' }}</td>
                                        <td class="py-3.5 px-4 text-right font-black text-slate-900">Rp {{ number_format($r->net_sales, 0, ',', '.') }}</td>
                                        <td class="py-3.5 px-4 text-center font-mono">{{ number_format($r->transaction_count) }}</td>
                                        <td class="py-3.5 px-4 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $r->source->value === 'integrated' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                                {{ $r->source->label() }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-400 text-[11px] whitespace-nowrap">
                                            {{ $r->reported_at->format('d/m/Y H:i') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-slate-400">
                                            Belum ada riwayat laporan omzet bulanan yang tercatat.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
