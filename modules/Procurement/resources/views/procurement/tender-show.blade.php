@extends('layouts.app')
@section('title', 'Tender '.$tender->number)
@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">🏛️ Tender: {{ $tender->title }}</h1>
            <p class="text-sm text-slate-400 mt-1 font-mono">{{ $tender->number }} · {{ $tender->type === 'closed' ? 'Tertutup (blind bid)' : 'Terbuka' }} · {{ $tender->status }}</p>
            <p class="text-xs text-slate-500 mt-1">Periode segel: {{ $tender->bids_open_at?->format('d M Y H:i') }} → {{ $tender->bids_close_at?->format('d M Y H:i') }} · Bobot: {{ json_encode($tender->criteria) }}</p>
        </div>
        <a href="{{ route('procurement.dashboard') }}" class="text-sm text-indigo-400 hover:text-indigo-300">← Dashboard</a>
    </div>

    <div class="flex flex-wrap gap-3">
        @if($tender->status === 'opened')
            <form method="POST" action="{{ route('procurement.tenders.evaluate', $tender) }}">
                @csrf
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">⚖️ Evaluasi Berbobot</button>
            </form>
        @elseif($tender->status === 'bidding' && now()->greaterThanOrEqualTo($tender->bids_close_at))
            <form method="POST" action="{{ route('procurement.tenders.open', $tender) }}">
                @csrf
                <button class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm">🔓 Buka Segel Bersamaan</button>
            </form>
        @endif
    </div>

    <section class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700"><h2 class="font-semibold text-white">🔐 Penawaran (segel SHA-256)</h2></div>
        <table class="w-full text-sm"><thead class="bg-slate-900/60 text-xs text-slate-400 uppercase"><tr><th class="px-4 py-3 text-left">Pemasok</th><th class="px-4 py-3 text-left">Segel</th><th class="px-4 py-3 text-left">Disegel</th><th class="px-4 py-3 text-left">Dibuka</th><th class="px-4 py-3 text-right">Skor</th><th class="px-4 py-3 text-left">Menang</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-slate-700/60">
            @forelse($tender->bids as $bid)
                <tr>
                    <td class="px-4 py-3 text-slate-200">{{ $bid->supplier?->name ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-[10px] text-slate-500">{{ $bid->opened_at ? json_encode($bid->offer['total_price_idr'] ?? '-') : substr($bid->seal_hash, 0, 24).'…' }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $bid->sealed_at?->format('d M H:i') }}</td>
                    <td class="px-4 py-3 text-xs {{ $bid->opened_at ? 'text-emerald-400' : 'text-amber-400' }}">{{ $bid->opened_at?->format('d M H:i') ?? 'tersegel' }}</td>
                    <td class="px-4 py-3 text-right text-white">{{ $bid->total_score !== null ? (float) $bid->total_score : '—' }}</td>
                    <td class="px-4 py-3">{{ $bid->is_winner ? '🏆 YA' : '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($tender->status === 'evaluated' && ! $bid->is_winner)
                            <form method="POST" action="{{ route('procurement.tenders.award', $tender) }}" class="inline">
                                @csrf
                                <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                <input name="reason" required placeholder="Alasan (wajib)" class="px-2 py-1 bg-slate-900 border border-slate-600 rounded text-white text-xs w-40">
                                <button class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs">Tetapkan</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Belum ada peserta mengirim segel.</td></tr>
            @endforelse
        </tbody></table>
    </section>

    <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
        <h2 class="font-semibold text-white mb-3">✉️ Kirim Segel Penawaran (blind bid)</h2>
        @if($tender->status === 'bidding')
            <form method="POST" action="{{ route('procurement.tenders.seal', $tender) }}" class="space-y-2">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <select name="supplier_id" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                        @foreach(\Modules\Supplier\Domain\Models\Supplier::where('is_active', true)->orderBy('name')->get() as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <input name="offer" required placeholder='{"total_price_idr": 10000000, "lead_time_days": 14}' class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm font-mono text-xs">
                </div>
                <button class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm">🔒 Kirim Segel (tidak terbaca s.d. dibuka)</button>
            </form>
        @else
            <p class="text-sm text-slate-500">Tender tidak dalam masa penawaran.</p>
        @endif
    </section>
</div>
@endsection
