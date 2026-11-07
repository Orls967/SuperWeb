@props(['item', 'isCorporate' => false])

<div class="flex items-center justify-between p-3.5 bg-white border border-slate-200 rounded-xl hover:border-slate-300 transition">
    <div class="flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold {{ $isCorporate ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
            {{ $isCorporate ? 'CORP' : 'GUEST' }}
        </span>
        <div>
            <h4 class="text-xs font-semibold text-slate-800">{{ $item['description'] ?? 'Item Transaksi' }}</h4>
            <span class="text-[11px] text-slate-400 font-mono">{{ $item['category'] ?? 'ROOM_CHARGE' }} • ID: {{ $item['line_id'] ?? '-' }}</span>
        </div>
    </div>
    <div class="text-right">
        <span class="text-xs font-bold font-mono text-slate-900 block">IDR {{ number_format((float) ($item['amount_idr'] ?? 0), 0, ',', '.') }}</span>
        <span class="text-[10px] text-slate-400 block">{{ $isCorporate ? 'Piutang City Ledger' : 'Kasir Personal Tamu' }}</span>
    </div>
</div>
