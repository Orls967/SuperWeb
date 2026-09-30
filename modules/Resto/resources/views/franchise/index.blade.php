<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Waralaba & Royalti Franchise
                </h2>
                <p class="text-sm text-slate-500 mt-1">Kelola kontrak kemitraan, bagi hasil omzet, dan posting otomatis royalti grup holding</p>
            </div>
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('resto.franchise.royalty.run') }}" class="flex items-center gap-2">
                    @csrf
                    <input type="date" name="date" value="{{ date('Y-m-d') }}" class="rounded-xl border-slate-200 text-xs font-semibold text-slate-700 focus:ring-teal-500 focus:border-teal-500 py-2">
                    <button type="submit" class="px-4 py-2 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-teal-500/20 transition-all flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span>Jalankan Posting Royalti</span>
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

            <!-- Form Buat Kontrak Baru -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6" x-data="{ openForm: false }">
                <div class="flex items-center justify-between cursor-pointer" @click="openForm = !openForm">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Registrasi Kontrak Franchise Outlet</h3>
                            <p class="text-xs text-slate-500">Tentukan persentase royalti dan dana pemasaran nasional untuk outlet mitra</p>
                        </div>
                    </div>
                    <button type="button" class="text-xs font-semibold text-teal-600 hover:text-teal-700">
                        <span x-show="!openForm">+ Buka Form</span>
                        <span x-show="openForm">&minus; Sembunyikan</span>
                    </button>
                </div>

                <form x-show="openForm" x-cloak method="POST" action="{{ route('resto.franchise.contract.store') }}" class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Outlet Mitra *</label>
                        <select name="outlet_id" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-teal-500 focus:border-teal-500">
                            <option value="">-- Pilih Outlet --</option>
                            @foreach($outlets as $o)
                                <option value="{{ $o->id }}">{{ $o->name }} ({{ $o->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Mitra Franchisee (User Akun)</label>
                        <select name="franchisee_user_id" class="w-full rounded-xl border-slate-200 text-xs focus:ring-teal-500 focus:border-teal-500">
                            <option value="">-- Pilih Akun Mitra (Opsional) --</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Royalti Penjualan Bersih (%) *</label>
                        <input type="number" step="0.1" min="0" max="50" name="royalty_percent" value="5.0" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-teal-500 focus:border-teal-500" placeholder="contoh: 5.0">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Marketing / National Ad Fee (%) *</label>
                        <input type="number" step="0.1" min="0" max="50" name="marketing_fee_percent" value="2.0" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-teal-500 focus:border-teal-500" placeholder="contoh: 2.0">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Mulai Berlaku *</label>
                        <input type="date" name="valid_from" value="{{ date('Y-m-d') }}" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-teal-500 focus:border-teal-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Berlaku Hingga *</label>
                        <input type="date" name="valid_until" value="{{ date('Y-m-d', strtotime('+3 years')) }}" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-teal-500 focus:border-teal-500">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3 flex justify-end pt-2">
                        <button type="submit" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-teal-500/10">
                            Simpan Kontrak Franchise
                        </button>
                    </div>
                </form>
            </div>

            <!-- Daftar Kontrak Aktif -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm">Daftar Kontrak Waralaba / Franchise</h3>
                        <p class="text-xs text-slate-500">Kontrak aktif yang diproses saat penutupan harian untuk auto-billing royalti</p>
                    </div>
                    <span class="text-xs font-bold text-slate-400">{{ count($contracts) }} Kontrak</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50/70 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                <th class="p-4">No. Kontrak</th>
                                <th class="p-4">Outlet Resto</th>
                                <th class="p-4">Mitra Pemilik</th>
                                <th class="p-4 text-center">Royalti (%)</th>
                                <th class="p-4 text-center">Marketing (%)</th>
                                <th class="p-4">Masa Berlaku</th>
                                <th class="p-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($contracts as $c)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="p-4 font-mono font-bold text-teal-700 text-xs">
                                        {{ $c->contract_number }}
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-slate-800">{{ $c->outlet?->name }}</div>
                                        <div class="text-xs text-slate-400 font-mono">{{ $c->outlet?->code }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-semibold text-slate-700">{{ $c->franchisee?->name ?? 'Internal / Kemitraan' }}</div>
                                        <div class="text-xs text-slate-400 font-mono">{{ $c->franchisee?->email ?? '-' }}</div>
                                    </td>
                                    <td class="p-4 text-center font-black text-amber-700">
                                        {{ (float) $c->royalty_percent }}%
                                    </td>
                                    <td class="p-4 text-center font-black text-blue-700">
                                        {{ (float) $c->marketing_fee_percent }}%
                                    </td>
                                    <td class="p-4 text-xs text-slate-600">
                                        {{ $c->valid_from->format('d/m/Y') }} s/d {{ $c->valid_until->format('d/m/Y') }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold {{ $c->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $c->is_active ? 'Aktif' : 'Non-Aktif' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-400 italic">Belum ada kontrak franchise yang terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Log History Posting Royalti -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900 text-sm">Riwayat Pembebanan Royalti & Marketing Fee</h3>
                    <p class="text-xs text-slate-500">Mutasi jurnal akuntansi double-entry yang dibukukan ke akun pendapatan holding grup</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50/70 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                <th class="p-4">Tanggal Buku</th>
                                <th class="p-4">Outlet</th>
                                <th class="p-4 text-right">Penjualan Bersih (Net)</th>
                                <th class="p-4 text-right">Royalti (IDR)</th>
                                <th class="p-4 text-right">Marketing Fee (IDR)</th>
                                <th class="p-4 text-right">Total Disetor Holding</th>
                                <th class="p-4 text-center">Jurnal Ledger</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($postings as $p)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="p-4 font-mono text-xs text-slate-700">
                                        {{ $p->date->format('d/m/Y') }}
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-slate-800">{{ $p->outlet?->name }}</div>
                                        <div class="text-xs text-slate-400 font-mono">{{ $p->contract?->contract_number }}</div>
                                    </td>
                                    <td class="p-4 text-right font-medium text-slate-700">
                                        Rp {{ number_format($p->net_sales, 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-right font-bold text-amber-700">
                                        Rp {{ number_format($p->royalty_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-right font-bold text-blue-700">
                                        Rp {{ number_format($p->marketing_fee_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-right font-black text-slate-900">
                                        Rp {{ number_format($p->total_due, 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                            #{{ $p->ledger_transaction_id ?? $p->id }} Posted
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-400 italic">Belum ada pembebanan royalti yang dibukukan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
