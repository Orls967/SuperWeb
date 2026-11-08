@props([
    'milestones' => []
])

<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        @foreach($milestones as $ms)
            @php
                $isPaid = ($ms['status'] ?? 'PENDING') === 'PAID';
                $isInvoiced = ($ms['status'] ?? 'PENDING') === 'INVOICED';
            @endphp
            <div class="bg-slate-900/70 border {{ $isPaid ? 'border-emerald-500/50' : ($isInvoiced ? 'border-amber-500/50' : 'border-slate-700') }} rounded-xl p-4 space-y-3 relative overflow-hidden">
                <div class="absolute top-0 right-0 px-2 py-0.5 {{ $isPaid ? 'bg-emerald-500 text-slate-950' : ($isInvoiced ? 'bg-amber-500 text-slate-950' : 'bg-slate-750 text-slate-400') }} font-bold text-[10px] rounded-bl">
                    {{ $ms['status'] ?? 'PENDING' }}
                </div>
                
                <div class="text-xs font-semibold text-slate-400">Termin {{ $ms['milestone_index'] ?? $loop->iteration }} ({{ $ms['percentage'] ?? 25 }}%)</div>
                <div class="text-lg font-bold text-white font-mono">IDR {{ number_format($ms['amount_idr'] ?? 0, 0, ',', '.') }}</div>
                
                <div class="text-xs text-slate-400 space-y-0.5">
                    <div>Jatuh Tempo: <span class="text-slate-300 font-mono">{{ $ms['due_date'] ?? '-' }}</span></div>
                    @if($isPaid)
                        <div class="text-emerald-400 font-medium">&check; Dibayar: {{ $ms['paid_at'] ?? 'Terkonfirmasi' }}</div>
                    @else
                        <div class="text-amber-400 font-medium">&bull; Menunggu Pembayaran</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
