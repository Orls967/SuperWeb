<div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4" x-data="crowdSafetyOperatorActionsComponent()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="p-1 rounded-md bg-emerald-500/20 text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
                Aksi Cepat Intervensi Keselamatan Operator Lapangan
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Tombol pintas kendali darurat untuk instruksi lapangan langsung dari pos pengawas (crowd safety control).</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-sky-500/20 text-sky-300 border border-sky-500/40">
            Operator Siaga
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <button type="button" @click="dispatchBroadcast('SLOW_ENTRY')" class="p-3 bg-amber-950/40 border border-amber-500/40 hover:bg-amber-900/50 rounded-xl text-left transition-colors">
            <span class="font-bold text-amber-300 block mb-1">Perlambat Ingress Masuk</span>
            <span class="text-slate-400 text-[11px] block">Batasi turnstile menjadi 1 rotasi per 10 detik guna memberi jeda kepadatan.</span>
        </button>

        <button type="button" @click="dispatchBroadcast('OPEN_SECONDARY')" class="p-3 bg-sky-950/40 border border-sky-500/40 hover:bg-sky-900/50 rounded-xl text-left transition-colors">
            <span class="font-bold text-sky-300 block mb-1">Buka Gerbang Sekunder</span>
            <span class="text-slate-400 text-[11px] block">Alihkan antrean masuk ke pintu samping barat yang masih kosong.</span>
        </button>

        <button type="button" @click="dispatchBroadcast('AUDIO_ANNOUNCE')" class="p-3 bg-purple-950/40 border border-purple-500/40 hover:bg-purple-900/50 rounded-xl text-left transition-colors">
            <span class="font-bold text-purple-300 block mb-1">Siarkan Pesan Suara Tenang</span>
            <span class="text-slate-400 text-[11px] block">Putar rekaman instruksi audio ramah agar pengunjung tidak saling mendorong.</span>
        </button>
    </div>
</div>

<script>
function crowdSafetyOperatorActionsComponent() {
    return {
        dispatchBroadcast(type) {
            alert('Instruksi operator [' + type + '] berhasil disiarkan.');
        }
    }
}
</script>
