<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Tagihan Bulanan & Piutang Mall (Billing & AR)
                </h2>
                <p class="text-sm text-slate-500 mt-1">Otomasi penagihan sewa, service charge, utilitas, penalti keterlambatan, dan pemantauan umur piutang</p>
            </div>
            <div class="flex items-center gap-3">
                <form action="{{ route('mall.billing.generate') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="month" value="{{ $period }}">
                    @if($propertyId)
                        <input type="hidden" name="property_id" value="{{ $propertyId }}">
                    @endif
                    <button type="submit" class="px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Terbitkan Invoice Bulan Ini</span>
                    </button>
                </form>

                <form action="{{ route('mall.billing.apply-penalties') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 bg-gradient-to-r from-rose-600 to-amber-600 hover:from-rose-700 hover:to-amber-700 text-white rounded-xl text-xs font-bold shadow-md shadow-rose-500/20 transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span>Terapkan Denda Harian</span>
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

            @if(session('error'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-center justify-between">
                    <span>{{ session('error') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-rose-500">&times;</button>
                </div>
            @endif

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Ditagih ({{ $period }})</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-2">Rp {{ number_format($overview['total_billed'], 0, ',', '.') }}</h3>
                    <p class="text-xs text-slate-500 mt-1">{{ count($overview['recent_invoices']) }} invoice diterbitkan</p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Penerimaan Terkumpul</p>
                    <h3 class="text-2xl font-black text-emerald-600 mt-2">Rp {{ number_format($overview['total_collected'], 0, ',', '.') }}</h3>
                    <p class="text-xs text-emerald-500 font-semibold mt-1">{{ $overview['collection_rate'] }}% tingkat penagihan</p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Piutang Tertunggak</p>
                    <h3 class="text-2xl font-black text-rose-600 mt-2">Rp {{ number_format($overview['total_outstanding'], 0, ',', '.') }}</h3>
                    <p class="text-xs text-rose-500 mt-1">{{ $overview['status_counts']['overdue'] }} invoice lewat jatuh tempo</p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Denda Dikenakan</p>
                    <h3 class="text-2xl font-black text-amber-600 mt-2">Rp {{ number_format($overview['total_penalties'], 0, ',', '.') }}</h3>
                    <p class="text-xs text-slate-500 mt-1">Tarif 0.1%/hari bertingkat</p>
                </div>
            </div>

            <!-- Aging Receivable Matrix -->
            <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="font-bold text-slate-800 text-sm">Analisis Umur Piutang (Aging Accounts Receivable)</h4>
                        <p class="text-xs text-slate-500">Klasifikasi keterlambatan pelunasan tagihan tenant mall</p>
                    </div>
                    <span class="text-xs font-bold text-slate-600 bg-slate-100 px-3 py-1 rounded-full">
                        Total Piutang: Rp {{ number_format($aging['summary']['total_outstanding'], 0, ',', '.') }}
                    </span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="p-3.5 rounded-xl bg-blue-50/60 border border-blue-100">
                        <span class="text-xs font-bold text-blue-600 uppercase">Lancar (0 - 30 Hari)</span>
                        <div class="text-lg font-black text-blue-900 mt-1">Rp {{ number_format($aging['summary']['days_1_30'] + $aging['summary']['current'], 0, ',', '.') }}</div>
                        <span class="text-[11px] text-blue-500">{{ $aging['counts']['days_1_30'] + $aging['counts']['current'] }} Invoice</span>
                    </div>

                    <div class="p-3.5 rounded-xl bg-amber-50/60 border border-amber-100">
                        <span class="text-xs font-bold text-amber-600 uppercase">Perhatian (31 - 60 Hari)</span>
                        <div class="text-lg font-black text-amber-900 mt-1">Rp {{ number_format($aging['summary']['days_31_60'], 0, ',', '.') }}</div>
                        <span class="text-[11px] text-amber-500">{{ $aging['counts']['days_31_60'] }} Invoice</span>
                    </div>

                    <div class="p-3.5 rounded-xl bg-orange-50/60 border border-orange-100">
                        <span class="text-xs font-bold text-orange-600 uppercase">Kritis (61 - 90 Hari)</span>
                        <div class="text-lg font-black text-orange-900 mt-1">Rp {{ number_format($aging['summary']['days_61_90'], 0, ',', '.') }}</div>
                        <span class="text-[11px] text-orange-500">{{ $aging['counts']['days_61_90'] }} Invoice</span>
                    </div>

                    <div class="p-3.5 rounded-xl bg-rose-50/60 border border-rose-100">
                        <span class="text-xs font-bold text-rose-600 uppercase">Macet (> 90 Hari)</span>
                        <div class="text-lg font-black text-rose-900 mt-1">Rp {{ number_format($aging['summary']['days_over_90'], 0, ',', '.') }}</div>
                        <span class="text-[11px] text-rose-500">{{ $aging['counts']['days_over_90'] }} Invoice (Suspend)</span>
                    </div>
                </div>
            </div>

            <!-- Filter & Invoices Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <form method="GET" action="{{ route('mall.billing.index') }}" class="flex flex-wrap items-center gap-2">
                        <input type="month" name="month" value="{{ $period }}" class="text-xs rounded-xl border-slate-200 px-3 py-2">
                        @if($properties->count() > 1)
                            <select name="property_id" class="text-xs rounded-xl border-slate-200 px-3 py-2">
                                <option value="">Semua Properti</option>
                                @foreach($properties as $prop)
                                    <option value="{{ $prop->id }}" {{ $propertyId == $prop->id ? 'selected' : '' }}>{{ $prop->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <select name="status" class="text-xs rounded-xl border-slate-200 px-3 py-2">
                            <option value="all">Semua Status</option>
                            <option value="issued" {{ $statusFilter == 'issued' ? 'selected' : '' }}>Diterbitkan (Belum Lunas)</option>
                            <option value="partially_paid" {{ $statusFilter == 'partially_paid' ? 'selected' : '' }}>Dibayar Sebagian</option>
                            <option value="paid" {{ $statusFilter == 'paid' ? 'selected' : '' }}>Lunas</option>
                            <option value="overdue" {{ $statusFilter == 'overdue' ? 'selected' : '' }}>Tertunggak</option>
                        </select>
                        <button type="submit" class="px-3.5 py-2 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-700">Filter</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500 uppercase font-semibold text-[11px] border-b border-slate-100">
                                <th class="py-3 px-4">No. Invoice</th>
                                <th class="py-3 px-4">Tenant / Brand</th>
                                <th class="py-3 px-4">Unit</th>
                                <th class="py-3 px-4 text-right">Subtotal</th>
                                <th class="py-3 px-4 text-right">Denda</th>
                                <th class="py-3 px-4 text-right">Total Tagihan</th>
                                <th class="py-3 px-4 text-right">Terbayar</th>
                                <th class="py-3 px-4 text-right">Sisa</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4">Jatuh Tempo</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($invoices as $inv)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-3.5 px-4 font-mono font-bold text-blue-600">
                                        <a href="{{ route('mall.invoices.show', $inv->id) }}" class="hover:underline">
                                            {{ $inv->invoice_number }}
                                        </a>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-800">{{ $inv->tenant?->brand_name }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $inv->tenant?->company_name }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 font-medium">{{ $inv->lease?->unit?->unit_number ?? '-' }}</td>
                                    <td class="py-3.5 px-4 text-right font-medium">Rp {{ number_format($inv->subtotal, 0, ',', '.') }}</td>
                                    <td class="py-3.5 px-4 text-right font-medium {{ $inv->penalty_amount > 0 ? 'text-rose-600 font-bold' : 'text-slate-400' }}">
                                        Rp {{ number_format($inv->penalty_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold text-slate-900">Rp {{ number_format($inv->total_amount, 0, ',', '.') }}</td>
                                    <td class="py-3.5 px-4 text-right font-medium text-emerald-600">Rp {{ number_format($inv->paid_amount, 0, ',', '.') }}</td>
                                    <td class="py-3.5 px-4 text-right font-black {{ $inv->remainingAmount() > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                        Rp {{ number_format($inv->remainingAmount(), 0, ',', '.') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $inv->status->badgeClass() }}">
                                            {{ $inv->status->label() }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 whitespace-nowrap">
                                        {{ $inv->due_date->format('d/m/Y') }}
                                        @if($inv->isOverdue())
                                            <span class="text-[10px] font-bold text-rose-500 block">Lewat {{ $inv->daysOverdue() }} hr</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="{{ route('mall.invoices.show', $inv->id) }}" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Lihat Rincian">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            </a>
                                            @if($inv->remainingAmount() > 0)
                                                <form action="{{ route('mall.invoices.auto-debit', $inv->id) }}" method="POST" onsubmit="return confirm('Debet otomatis saldo dompet tenant sekarang?');">
                                                    @csrf
                                                    <button type="submit" class="p-1.5 text-indigo-500 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition" title="Auto Debit Dompet">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="py-8 text-center text-slate-400">
                                        Belum ada tagihan sewa bulanan untuk periode {{ $period }}. Klik tombol "Terbitkan Invoice Bulan Ini".
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($invoices->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $invoices->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
