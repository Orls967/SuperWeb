@extends('layouts.app')
@section('title', 'RFQ '.$rfq->number)
@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">📢 {{ $rfq->title }}</h1>
            <p class="text-sm text-slate-400 mt-1 font-mono">{{ $rfq->number }} · status {{ $rfq->status }} · tutup {{ $rfq->closes_at?->format('d M Y H:i') }}</p>
        </div>
        <a href="{{ route('procurement.dashboard') }}" class="text-sm text-indigo-400 hover:text-indigo-300">← Dashboard</a>
    </div>

    <section class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700"><h2 class="font-semibold text-white">📊 Matriks Perbandingan Penawaran (harga · lead time · skor pemasok)</h2></div>
        <table class="w-full text-sm"><thead class="bg-slate-900/60 text-xs text-slate-400 uppercase"><tr><th class="px-4 py-3 text-left">Pemasok</th><th class="px-4 py-3 text-right">Harga (IDR)</th><th class="px-4 py-3 text-right">Lead (hari)</th><th class="px-4 py-3 text-right">Skor Pemasok</th><th class="px-4 py-3 text-right">Skor Komposit</th><th class="px-4 py-3 text-left">Terpilih</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-slate-700/60">
            @forelse($comparison as $row)
                <tr class="{{ $loop->first ? 'bg-emerald-500/5' : '' }}">
                    <td class="px-4 py-3 text-slate-200">{{ $row['supplier_name'] }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['price_idr']) }}</td>
                    <td class="px-4 py-3 text-right">{{ $row['lead_time_days'] }}</td>
                    <td class="px-4 py-3 text-right">{{ $row['supplier_score'] }}</td>
                    <td class="px-4 py-3 text-right font-semibold {{ $loop->first ? 'text-emerald-400' : 'text-slate-300' }}">{{ $row['composite_score'] }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $rfq->quotes->firstWhere('supplier_id', $row['supplier_id'])?->is_selected ? '✓ Terpilih' : '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($rfq->status === 'open')
                            <form method="POST" action="{{ route('procurement.rfqs.award', $rfq) }}" class="inline" onsubmit="return confirm('Tetapkan pemenang dengan alasan wajib?')">
                                @csrf
                                <input type="hidden" name="quote_id" value="{{ $rfq->quotes->firstWhere('supplier_id', $row['supplier_id'])?->id }}">
                                <input name="reason" required placeholder="Alasan (wajib)" class="px-2 py-1 bg-slate-900 border border-slate-600 rounded text-white text-xs w-40">
                                <button class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs">Menang</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Belum ada penawaran masuk.</td></tr>
            @endforelse
        </tbody></table>
    </section>

    <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
        <h2 class="font-semibold text-white mb-3">✍️ Kirim Penawaran (pemasok diundang)</h2>
        @if($rfq->status === 'open')
            <form method="POST" action="{{ route('procurement.quotes.store', $rfq) }}" class="grid grid-cols-5 gap-2">
                @csrf
                <select name="supplier_id" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm col-span-2">
                    @foreach($rfq->invitations as $inv)
                        <option value="{{ $inv->supplier_id }}">{{ $inv->supplier?->name ?? $inv->supplier_id }} · {{ $inv->status }}</option>
                    @endforeach
                </select>
                <input name="total_price_idr" type="number" min="0" required placeholder="Total harga (IDR)" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="lead_time_days" type="number" min="1" value="7" required placeholder="Lead time (hari)" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Kirim</button>
            </form>
        @else
            <p class="text-sm text-slate-500">RFQ tidak terbuka untuk penawaran.</p>
        @endif
    </section>
</div>
@endsection
