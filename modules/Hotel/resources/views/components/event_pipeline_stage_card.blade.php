@props([
    'leadCode',
    'eventType',
    'title',
    'companyName',
    'expectedPax',
    'estimatedBudget',
    'status' => 'NEW',
    'daysUntilEvent' => 60
])

<div class="bg-slate-800/90 border border-slate-700 rounded-lg p-3 shadow-sm hover:border-slate-600 transition space-y-2">
    <div class="flex items-center justify-between text-xs">
        <span class="px-2 py-0.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded font-medium uppercase font-mono">{{ $eventType }}</span>
        <span class="text-slate-400 font-mono">H-{{ $daysUntilEvent }}</span>
    </div>
    
    <h4 class="font-medium text-slate-100 text-sm leading-snug">{{ $title }}</h4>
    <p class="text-xs text-slate-400">{{ $companyName }} &bull; {{ $expectedPax }} Pax</p>
    
    <div class="pt-2 border-t border-slate-700/60 flex items-center justify-between text-xs">
        <span class="text-slate-300 font-mono">IDR {{ number_format($estimatedBudget, 0, ',', '.') }}</span>
        <span class="px-1.5 py-0.5 text-[10px] bg-slate-700 text-slate-300 rounded font-semibold">{{ $status }}</span>
    </div>
</div>
