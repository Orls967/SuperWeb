@props(['milestones'])

<div class="space-y-3">
    <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
        <span>Tahapan Pelepasan Escrow Multi-Vendor</span>
        <span class="text-indigo-600 font-mono">Real-Time Verification</span>
    </div>
    <div class="relative border-l-2 border-indigo-200 ml-4 space-y-4 py-1">
        @foreach($milestones as $m)
            <div class="relative pl-6">
                <span class="absolute -left-[9px] top-1 w-4 h-4 rounded-full border-2 border-white {{ ($m['status'] ?? '') === 'RELEASED' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-900">{{ $m['vendor_name'] ?? 'Vendor' }}</span>
                        <span class="text-xs font-mono font-semibold text-slate-700">IDR {{ number_format((float) ($m['amount_idr'] ?? 0), 0, ',', '.') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">{{ $m['condition'] ?? 'Verifikasi pemenuhan layanan' }}</p>
                    <div class="mt-2 flex items-center justify-between text-[11px]">
                        <span class="font-medium {{ ($m['status'] ?? '') === 'RELEASED' ? 'text-emerald-600' : 'text-slate-400' }}">
                            {{ ($m['status'] ?? '') === 'RELEASED' ? '✓ Dana Telah Diteruskan ke Vendor' : 'Tertahan di Rekening Escrow' }}
                        </span>
                        @if(($m['status'] ?? '') !== 'RELEASED')
                            <button class="px-2 py-1 bg-white border border-slate-300 rounded text-[10px] font-semibold text-slate-700 hover:bg-slate-100">
                                Verifikasi Bukti
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
