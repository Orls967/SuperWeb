@props([
    'serviceEvents' => []
])

<div class="space-y-3 font-sans">
    <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider pb-2 border-b border-slate-800">Timeline Penyajian Makanan & Jamuan</h4>
    
    <div class="relative pl-6 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-700">
        @forelse($serviceEvents as $event)
            <div class="relative space-y-0.5 text-xs">
                <span class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-amber-400 ring-4 ring-slate-900"></span>
                <div class="flex items-center gap-2">
                    <span class="font-mono font-bold text-amber-300">{{ $event['time'] ?? '18:00' }}</span>
                    <span class="font-semibold text-white">{{ $event['title'] ?? 'Sesi Penyajian' }}</span>
                </div>
                <p class="text-slate-400 text-[11px]">{{ $event['desc'] ?? '-' }}</p>
            </div>
        @empty
            <div class="text-xs text-slate-400">Belum ada jadwal sesi penyajian terperinci.</div>
        @endforelse
    </div>
</div>
