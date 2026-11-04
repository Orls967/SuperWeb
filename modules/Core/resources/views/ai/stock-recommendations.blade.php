@extends('layouts.app')

@section('title', 'Rekomendasi Stok Preskriptif')
@section('subtitle', 'Perencanaan Stok Adaptif Berbasis SES & Musiman (D64-3)')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('info'))
        <div class="p-4 rounded-xl bg-sky-500/10 border border-sky-500/30 text-sky-400 text-sm">
            {{ session('info') }}
        </div>
    @endif

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-6 shadow-xl">
        <h2 class="text-lg font-semibold text-white mb-4">Penerbitan Rekomendasi Stok Baru</h2>
        <form method="POST" action="{{ route('core.ai.stock-recommendations.store') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">Kode Item</label>
                <input type="text" name="item_code" required placeholder="SKU-..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-sm focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">Nama Item</label>
                <input type="text" name="item_name" required placeholder="Nama Produk..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-sm focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">Stok Saat Ini</label>
                <input type="number" name="current_stock" required min="0" placeholder="10" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-sm focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">Permintaan Harian Rata-rata</label>
                <input type="number" name="avg_daily_demand" required min="1" placeholder="50" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-sm focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">Lead Time (Hari)</label>
                <input type="number" name="lead_time_days" required min="1" placeholder="7" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-sm focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">Biaya per Unit (IDR)</label>
                <input type="number" name="unit_cost_idr" required min="100" placeholder="150000" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-sm focus:border-indigo-500">
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-medium text-sm transition">
                    Generate Rekomendasi Preskriptif
                </button>
            </div>
        </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 sm:p-6 border-b border-slate-800 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-white">Daftar Usulan Stok & PO</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950 text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="p-3 sm:p-4">Kode / Item</th>
                        <th class="p-3 sm:p-4">Stok / ROP</th>
                        <th class="p-3 sm:p-4">Usulan Order</th>
                        <th class="p-3 sm:p-4">Estimasi Biaya</th>
                        <th class="p-3 sm:p-4">Status & Approval</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($recommendations as $rec)
                        <tr class="hover:bg-slate-800/50">
                            <td class="p-3 sm:p-4 font-medium text-white">
                                <div>{{ $rec->item_name }}</div>
                                <div class="text-xs font-mono text-slate-500">{{ $rec->item_code }}</div>
                            </td>
                            <td class="p-3 sm:p-4 font-mono text-xs">
                                <div>Stok: {{ $rec->current_stock }}</div>
                                <div class="text-indigo-400">ROP: {{ $rec->calculated_rop }}</div>
                            </td>
                            <td class="p-3 sm:p-4 font-mono font-bold text-white">
                                {{ $rec->suggested_order_qty }} unit
                            </td>
                            <td class="p-3 sm:p-4 font-mono">
                                Rp {{ number_format($rec->estimated_cost_idr, 0, ',', '.') }}
                            </td>
                            <td class="p-3 sm:p-4">
                                @if($rec->status === 'PROPOSED')
                                    @if($rec->requires_human_approval)
                                        <form method="POST" action="{{ route('core.ai.stock-recommendations.approve', $rec) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold whitespace-nowrap">
                                                Approve PO (Planner Manager)
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-sky-400">Otonom (Dalam Plafon)</span>
                                    @endif
                                @else
                                    <span class="text-xs text-emerald-400 font-semibold font-mono">{{ $rec->approved_po_ref }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-6 text-center text-slate-500">Belum ada rekomendasi stok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

