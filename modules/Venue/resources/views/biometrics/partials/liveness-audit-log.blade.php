<div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4" x-data="livenessAuditLogComponent()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="p-1 rounded-md bg-cyan-500/20 text-cyan-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </span>
                Log Audit Uji Serangan Presentasi Biometrik (PAD Audit Trail)
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Riwayat deteksi serangan tiruan (kertas 2D, layar ponsel, topeng lateks) pada sensor kios turnstile.</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
            Akurasi Liveness: 99.8%
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-900 text-slate-400 font-mono uppercase text-[11px] border-b border-slate-800">
                <tr>
                    <th class="py-2.5 px-3">Waktu</th>
                    <th class="py-2.5 px-3">Gerbang</th>
                    <th class="py-2.5 px-3">Skor Liveness</th>
                    <th class="py-2.5 px-3">Reflektansi NIR</th>
                    <th class="py-2.5 px-3">Klasifikasi Deteksi</th>
                    <th class="py-2.5 px-3 text-right">Keputusan Gerbang</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/80 font-mono text-[11px]">
                <tr class="hover:bg-slate-900/50">
                    <td class="py-3 px-3 text-slate-400">21:42:05</td>
                    <td class="py-3 px-3 text-white">Turnstile-01</td>
                    <td class="py-3 px-3 text-emerald-400 font-bold">0.982</td>
                    <td class="py-3 px-3 text-slate-300">0.884</td>
                    <td class="py-3 px-3 text-emerald-400 font-sans">Wajah Asli (Live Human)</td>
                    <td class="py-3 px-3 text-right">
                        <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold">DIIZINKAN</span>
                    </td>
                </tr>
                <tr class="hover:bg-slate-900/50">
                    <td class="py-3 px-3 text-slate-400">21:39:18</td>
                    <td class="py-3 px-3 text-white">Turnstile-02</td>
                    <td class="py-3 px-3 text-rose-400 font-bold">0.245</td>
                    <td class="py-3 px-3 text-rose-300">0.120</td>
                    <td class="py-3 px-3 text-rose-400 font-sans">Serangan Replay Layar Ponsel (Moire)</td>
                    <td class="py-3 px-3 text-right">
                        <span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 font-bold">DITOLAK</span>
                    </td>
                </tr>
                <tr class="hover:bg-slate-900/50">
                    <td class="py-3 px-3 text-slate-400">21:35:44</td>
                    <td class="py-3 px-3 text-white">Turnstile-03</td>
                    <td class="py-3 px-3 text-emerald-400 font-bold">0.965</td>
                    <td class="py-3 px-3 text-slate-300">0.860</td>
                    <td class="py-3 px-3 text-emerald-400 font-sans">Wajah Asli (Live Human)</td>
                    <td class="py-3 px-3 text-right">
                        <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold">DIIZINKAN</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function livenessAuditLogComponent() {
    return {
        autoRefresh: true
    }
}
</script>
