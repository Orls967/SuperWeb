@extends('layouts.app')
@section('title', 'PR '.$requisition->number)
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">📄 {{ $requisition->title }}</h1>
            <p class="text-sm text-slate-400 mt-1 font-mono">{{ $requisition->number }} · {{ $requisition->source }} · {{ $requisition->status }}</p>
        </div>
        @if($requisition->status === 'pending_approval')
            <form method="POST" action="{{ route('procurement.requisitions.approve', $requisition) }}">
                @csrf
                <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm">✅ Setujui PR</button>
            </form>
        @endif
    </div>

    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden mb-6">
        <table class="w-full text-sm"><thead class="bg-slate-900/60 text-xs text-slate-400 uppercase"><tr><th class="px-4 py-3 text-left">Deskripsi</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Estimasi Harga/Unit</th><th class="px-4 py-3 text-right">Subtotal</th></tr></thead><tbody class="divide-y divide-slate-700/60">
            @foreach($requisition->lines as $line)
                <tr><td class="px-4 py-3 text-slate-200">{{ $line->description }}</td><td class="px-4 py-3 text-right">{{ $line->qty }} {{ $line->unit }}</td><td class="px-4 py-3 text-right">{{ number_format($line->estimated_unit_price_idr) }}</td><td class="px-4 py-3 text-right text-white">{{ number_format($line->qty * $line->estimated_unit_price_idr) }}</td></tr>
            @endforeach
        </tbody><tfoot class="bg-slate-900/60"><tr><td class="px-4 py-3 font-semibold text-slate-200" colspan="3">Total Estimasi</td><td class="px-4 py-3 text-right font-bold text-emerald-400">{{ number_format($requisition->total_estimated_idr) }}</td></tr></tfoot></table>
    </div>

    <div class="grid grid-cols-2 gap-4 text-sm">
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">Pusat Biaya</p><p class="text-white">{{ $requisition->budgetCenter?->name ?? '—' }}</p></div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">Disetujui</p><p class="text-white">{{ $requisition->approved_at?->format('d M Y H:i') ?? '—' }}</p></div>
    </div>
</div>
@endsection
