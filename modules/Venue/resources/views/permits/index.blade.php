<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Izin Keramaian & Kepatuhan — Compliance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex flex-col font-sans">
    <!-- Navbar -->
    <nav class="bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center space-x-4">
            <span class="text-emerald-400 font-black text-xl tracking-wider">SUPERWEB // VENUE</span>
            <span class="text-xs bg-cyan-950 text-cyan-400 border border-cyan-800 px-2 py-0.5 rounded font-mono font-semibold">LEGAL & COMPLIANCE</span>
        </div>
        <div class="flex items-center space-x-6 text-sm">
            <a href="/venue/events" class="text-slate-400 hover:text-white transition-colors">Acara & Tiket</a>
            <a href="/venue/gate" class="text-slate-400 hover:text-white transition-colors">Layar Gerbang 375px</a>
            <a href="/venue/zones" class="text-slate-400 hover:text-white transition-colors">Peta Kerumunan</a>
            <a href="/venue/tables" class="text-slate-400 hover:text-white transition-colors">Meja VIP</a>
            <a href="/venue/permits" class="text-emerald-400 font-bold border-b-2 border-emerald-400 pb-1">Izin Keramaian</a>
            <a href="/venue/reports" class="text-slate-400 hover:text-white transition-colors">Laporan & Audit</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-6 space-y-6">
        
        <!-- Alerts -->
        @if(session('success'))
            <div class="bg-emerald-950/80 border border-emerald-500 text-emerald-200 px-4 py-3 rounded-xl flex items-center justify-between shadow-lg">
                <span class="text-sm font-medium">{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
            </div>
        @endif

        <!-- Header -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-100 tracking-tight">Kepatuhan Hukum & Izin Keramaian Publik</h1>
                <p class="text-sm text-slate-400 mt-1">
                    Penerbitan surat izin kepolisian (Polres/Polda), audit damkar, jam malam kebisingan, serta integrasi polis asuransi keramaian (G7-K15 EventCoverBinder).
                </p>
            </div>
            <button onclick="document.getElementById('modal-issue-permit').classList.remove('hidden')" class="px-4 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-cyan-950 transition-all flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span>Terbitkan Izin Baru</span>
            </button>
        </div>

        <!-- Tabel Izin Keramaian -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-200">Daftar Surat Izin Keramaian Aktif</h2>
                <span class="text-xs text-slate-500 font-mono">D89-6 • Gapless Permit Hash Chain</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-950/60 text-slate-400 text-[11px] font-semibold uppercase tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">Nomor Izin & Tipe</th>
                            <th class="px-6 py-3.5">Otoritas Penerbit</th>
                            <th class="px-6 py-3.5">Acara Terkait</th>
                            <th class="px-6 py-3.5 text-center">Kapasitas Disetujui</th>
                            <th class="px-6 py-3.5 text-center">Batas Jam Malam</th>
                            <th class="px-6 py-3.5 text-center">Status</th>
                            <th class="px-6 py-3.5">Hash Rantai Kriptografis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @forelse($permits as $p)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-6 py-4">
                                    <div class="font-mono font-bold text-cyan-400 text-xs">{{ $p->permit_number }}</div>
                                    <div class="text-[11px] text-slate-500 uppercase">{{ $p->permit_type }}</div>
                                </td>
                                <td class="px-6 py-4 text-xs font-semibold text-slate-200">
                                    {{ $p->issuing_authority }}
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-300">
                                    <div class="font-bold">{{ $p->event?->title ?? '-' }}</div>
                                    <div class="text-[11px] font-mono text-slate-500">{{ $p->event?->event_code ?? '' }}</div>
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-xs tabular-nums text-slate-200 font-bold">
                                    {{ number_format($p->authorized_capacity, 0, ',', '.') }} orang
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-xs tabular-nums text-amber-400">
                                    {{ $p->approved_noise_curfew ?? '-' }} WIB
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $p->status === 'APPROVED' ? 'bg-emerald-950 text-emerald-400 border-emerald-800' : 'bg-slate-800 text-slate-400 border-slate-700' }}">
                                        {{ $p->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-mono text-[10px] text-slate-400 truncate max-w-xs">
                                    {{ $p->permit_hash }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-xs text-slate-500 italic">
                                    Belum ada catatan surat izin keramaian publik yang diterbitkan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Terbitkan Izin -->
        <div id="modal-issue-permit" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 max-w-lg w-full rounded-2xl p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-100">Penerbitan Surat Izin Keramaian</h3>
                    <button onclick="document.getElementById('modal-issue-permit').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
                </div>
                <form action="/venue/permits" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Pilih Acara</label>
                        <select name="event_id" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                            @foreach($events as $ev)
                                <option value="{{ $ev->id }}">{{ $ev->title }} ({{ $ev->event_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Tipe Izin</label>
                            <input type="text" name="permit_type" value="POLICE_CROWD_SAFETY" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Otoritas Penerbit</label>
                            <input type="text" name="issuing_authority" value="Polda Bali / Polres Badung" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Kapasitas Disetujui</label>
                            <input type="number" name="authorized_capacity" value="1500" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-emerald-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Jam Malam Kebisingan</label>
                            <input type="text" name="approved_noise_curfew" value="02:00" placeholder="HH:MM" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-amber-300">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Masa Berlaku Sampai (Expires At)</label>
                        <input type="date" name="expires_at" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                    </div>
                    <div class="pt-2 flex justify-end space-x-2">
                        <button type="button" onclick="document.getElementById('modal-issue-permit').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-bold rounded-xl">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold rounded-xl">Terbitkan Izin & Kaitkan Polis</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>

