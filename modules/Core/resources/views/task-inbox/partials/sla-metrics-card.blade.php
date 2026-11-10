<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    {{-- Rasio Kepatuhan SLA --}}
    <x-ds.card class="bg-slate-800/80 border-slate-700/60 p-5 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Kepatuhan SLA (SLA Adherence)</span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300">Target 95%</span>
        </div>

        <div class="flex items-baseline space-x-2">
            <span class="text-3xl font-extrabold text-white">96.8%</span>
            <span class="text-xs text-emerald-400 font-semibold">+1.4% MoM</span>
        </div>

        {{-- Progress Bar --}}
        <div class="w-full bg-slate-900 rounded-full h-2 overflow-hidden border border-slate-700/60">
            <div class="bg-emerald-500 h-2 rounded-full" style="width: 96.8%"></div>
        </div>

        <p class="text-[11px] text-slate-400">
            Dari total tiket disetujui, 96.8% diselesaikan tepat sebelum tenggat waktu kadaluwarsa.
        </p>
    </x-ds.card>

    {{-- Waktu Penyelesaian Rata-rata --}}
    <x-ds.card class="bg-slate-800/80 border-slate-700/60 p-5 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Median Turnaround Time</span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/20 text-blue-300">Cepat</span>
        </div>

        <div class="flex items-baseline space-x-2">
            <span class="text-3xl font-extrabold text-white">3.4</span>
            <span class="text-sm font-normal text-slate-400">Jam Operasional</span>
        </div>

        <div class="w-full bg-slate-900 rounded-full h-2 overflow-hidden border border-slate-700/60">
            <div class="bg-blue-500 h-2 rounded-full" style="width: 72%"></div>
        </div>

        <p class="text-[11px] text-slate-400">
            Waktu rata-rata dari tiket diterima hingga penandatanganan keputusan persetujuan.
        </p>
    </x-ds.card>

    {{-- Rasio Eskalasi Tiket --}}
    <x-ds.card class="bg-slate-800/80 border-slate-700/60 p-5 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Tingkat Eskalasi (Escalation Rate)</span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300">&lt; 5% Target</span>
        </div>

        <div class="flex items-baseline space-x-2">
            <span class="text-3xl font-extrabold text-white">2.1%</span>
            <span class="text-xs text-emerald-400 font-semibold">Terkendali</span>
        </div>

        <div class="w-full bg-slate-900 rounded-full h-2 overflow-hidden border border-slate-700/60">
            <div class="bg-amber-500 h-2 rounded-full" style="width: 21%"></div>
        </div>

        <p class="text-[11px] text-slate-400">
            Hanya 2.1% tugas yang membutuhkan intervensi supervisor di atas wewenang pemegang pertama.
        </p>
    </x-ds.card>
</div>

