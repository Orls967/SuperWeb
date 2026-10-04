@extends('layouts.app')

@section('title', 'Procurement')
@section('subtitle', 'PR → RFQ → Tender → PO · encumbrance · inbound logistics')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">🛒 Procurement Dashboard</h1>
            <p class="text-sm text-slate-400 mt-1">Spend analysis, siklus pembelian, anggaran terkunci (encumbrance).</p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">PR Terbuka</p><p class="text-2xl font-bold text-white">{{ number_format($stats['open_pr']) }}</p></div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">RFQ Terbuka</p><p class="text-2xl font-bold text-indigo-300">{{ number_format($stats['rfq_open']) }}</p></div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">PO Terbuka</p><p class="text-2xl font-bold text-amber-300">{{ number_format($stats['open_po']) }}</p></div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">PO Lewat Tanggal</p><p class="text-2xl font-bold {{ $stats['overdue_po'] > 0 ? 'text-red-400' : 'text-emerald-400' }}">{{ number_format($stats['overdue_po']) }}</p></div>
    </div>

    @if($stats['budget_warning'])
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-sm">⚠️ Ada pusat biaya yang ter-encumber melebihi anggaran tahunan.</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="lg:col-span-2 bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700">
                <h2 class="font-semibold text-white">PR Terbaru</h2>
                <button type="button" onclick="document.getElementById('pr-form').classList.toggle('hidden')" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs">+ Buat PR</button>
            </div>
            <form id="pr-form" method="POST" action="{{ route('procurement.requisitions.store') }}" class="hidden p-5 border-b border-slate-700 space-y-3">
                @csrf
                <input name="title" required placeholder="Judul purchase requisition" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <div class="grid grid-cols-3 gap-2">
                    <select name="source" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm"><option value="manual">Manual</option><option value="reorder_point">Reorder point</option><option value="mrp">MRP (Fase 36)</option></select>
                    <select name="budget_center_id" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm"><option value="">— Pusat biaya —</option>@foreach($budgetCenters as $bc)<option value="{{ $bc->id }}">{{ $bc->code }} · {{ $bc->name }}</option>@endforeach</select>
                    <input name="lines[0][description]" required placeholder="Item yang dibutuhkan" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <input name="lines[0][qty]" type="number" min="1" value="1" placeholder="Qty" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <input name="lines[0][estimated_unit_price_idr]" type="number" min="0" value="0" placeholder="Estimasi harga/unit IDR" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <input name="lines[0][unit]" value="pcs" placeholder="Satuan" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                </div>
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Buat & Ajukan Approval</button>
            </form>
            <table class="w-full text-sm">
                <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase"><tr><th class="px-4 py-3 text-left">Nomor</th><th class="px-4 py-3 text-left">Judul</th><th class="px-4 py-3 text-right">Estimasi</th><th class="px-4 py-3 text-left">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-700/60">
                    @forelse($prList as $pr)
                        <tr><td class="px-4 py-3 font-mono text-xs text-indigo-300">{{ $pr->number }}</td><td class="px-4 py-3 text-slate-200">{{ $pr->title }}</td><td class="px-4 py-3 text-right">{{ number_format($pr->total_estimated_idr) }}</td><td class="px-4 py-3 text-slate-400">{{ $pr->status }}</td></tr>
                    @empty<tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Belum ada PR.</td></tr>@endforelse
                </tbody>
            </table>
        </section>

        <section class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700">
                <h2 class="font-semibold text-white">Buat RFQ</h2>
                <button type="button" onclick="document.getElementById('rfq-form').classList.toggle('hidden')" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded text-xs">RFQ Baru</button>
            </div>
            <form id="rfq-form" method="POST" action="{{ route('procurement.rfqs.store') }}" class="hidden p-5 space-y-3">
                @csrf
                <input name="title" required placeholder="Judul RFQ" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="closes_at" type="datetime-local" required class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <select name="supplier_ids[]" multiple required class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm h-28">
                    @foreach(\Modules\Supplier\Domain\Models\Supplier::where('is_active', true)->whereIn('status', ['approved','preferred'])->orderBy('name')->get() as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }} ({{ $supplier->code }})</option>
                    @endforeach
                </select>
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Buat & Undang Pemasok</button>
            </form>
            <div class="divide-y divide-slate-700/60">
                @forelse($rfqList as $rfq)
                    <a href="{{ route('procurement.rfqs.show', $rfq) }}" class="block px-5 py-3 hover:bg-slate-700/30"><p class="font-mono text-xs text-indigo-300">{{ $rfq->number }}</p><p class="text-sm text-slate-200">{{ $rfq->title }}</p><p class="text-xs text-slate-500">status {{ $rfq->status }} · tutup {{ $rfq->closes_at?->format('d M H:i') }}</p></a>
                @empty<div class="px-5 py-8 text-center text-slate-500 text-sm">Belum ada RFQ.</div>@endforelse
            </div>
        </section>
    </div>

    <section class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700"><h2 class="font-semibold text-white">PO Terbaru</h2></div>
        <table class="w-full text-sm"><thead class="bg-slate-900/60 text-xs text-slate-400 uppercase"><tr><th class="px-4 py-3 text-left">Nomor</th><th class="px-4 py-3 text-left">Pemasok</th><th class="px-4 py-3 text-left">Judul</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-slate-700/60">
            @forelse($poList as $po)
                <tr><td class="px-4 py-3 font-mono text-xs text-indigo-300">{{ $po->number }}</td><td class="px-4 py-3 text-slate-300">{{ $po->supplier?->name }}</td><td class="px-4 py-3 text-slate-200">{{ $po->title }}</td><td class="px-4 py-3 text-right">{{ number_format($po->total_amount) }}</td><td class="px-4 py-3 text-slate-400">{{ $po->status }}</td><td class="px-4 py-3 text-right"><a href="{{ route('procurement.pos.show', $po) }}" class="text-indigo-300 hover:underline">Detail →</a></td></tr>
            @empty<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada PO.</td></tr>@endforelse
        </tbody></table>
    </section>
</div>
@endsection
