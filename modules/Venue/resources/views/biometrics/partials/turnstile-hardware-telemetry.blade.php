<div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4" x-data="turnstileHardwareTelemetryComponent()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="p-1 rounded-md bg-sky-500/20 text-sky-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
                Diagnostik Sensor IoT & Relay Solenoid Turnstile
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Telemetri tegangan arus, suhu motor aktuator, dan latensi elektromagnetik gerbang fisik.</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
            Bus CAN: 1 Mbps (Sinkron)
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs font-mono">
        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-slate-400 block text-[11px]">Tegangan Solenoid:</span>
            <strong class="text-white">24.2 VDC</strong>
            <span class="text-[10px] text-emerald-400 block mt-1">Normal Operasional</span>
        </div>
        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-slate-400 block text-[11px]">Suhu Aktuator:</span>
            <strong class="text-amber-400">38.4 °C</strong>
            <span class="text-[10px] text-slate-400 block mt-1">Batas Maks 65 °C</span>
        </div>
        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-slate-400 block text-[11px]">Latensi Rotasi:</span>
            <strong class="text-emerald-400">140 ms</strong>
            <span class="text-[10px] text-emerald-400 block mt-1">Respons Instan</span>
        </div>
        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-slate-400 block text-[11px]">Status Anti-Passback:</span>
            <strong class="text-sky-400">ENABLED</strong>
            <span class="text-[10px] text-sky-400 block mt-1">Window: 60 Detik</span>
        </div>
    </div>
</div>

<script>
function turnstileHardwareTelemetryComponent() {
    return {
        telemetryInterval: 2000
    }
}
</script>
