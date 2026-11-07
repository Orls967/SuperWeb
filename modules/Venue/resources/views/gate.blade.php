<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gerbang Akses Venue (375px Gate Console)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tabular-nums { font-variant-numeric: tabular-nums; }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.05); opacity: 0.4; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }
        .scanning-pulse { animation: pulse-ring 2s infinite ease-in-out; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex flex-col items-center justify-start p-2 sm:p-4 font-sans select-none">
    <!-- Kontainer Layar 375px Mobile Standar Gerbang Masuk -->
    <div class="w-full max-w-[375px] bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden min-h-[667px] relative">
        
        <!-- Header Status Bar -->
        <header class="bg-slate-950/80 backdrop-blur-md px-4 py-3 border-b border-slate-800 flex items-center justify-between sticky top-0 z-20">
            <div class="flex items-center space-x-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-bold tracking-wider text-slate-300 uppercase">GATE-MAIN-01 • Turnstile Scanner</span>
            </div>
            <div class="text-right">
                <span class="text-[11px] font-mono text-slate-400 tabular-nums" id="clock-display">{{ date('H:i:s') }}</span>
                <span class="text-[10px] block text-emerald-400 font-semibold">ONLINE • ENCRYPTED</span>
            </div>
        </header>

        <!-- Toast Notifikasi (State 5: Success Toast) -->
        <div id="toast-container" class="hidden absolute top-14 left-3 right-3 z-30 transition-all duration-300">
            <div id="toast-message" class="bg-emerald-900/90 border border-emerald-500 text-emerald-100 text-xs px-3 py-2.5 rounded-xl shadow-lg flex items-center justify-between">
                <span id="toast-text" class="font-medium">Tiket berhasil divalidasi</span>
                <button onclick="document.getElementById('toast-container').classList.add('hidden')" class="text-emerald-300 hover:text-white text-sm font-bold ml-2">&times;</button>
            </div>
        </div>

        <!-- Banner Hasil Pindaian Utama (Besar & Kontras Tinggi) -->
        <div class="p-4 bg-slate-950/50 border-b border-slate-800 text-center">
            <div id="scan-result-card" class="rounded-xl p-5 border transition-all duration-200 bg-slate-900 border-slate-700">
                <div id="result-icon-container" class="w-16 h-16 mx-auto rounded-full flex items-center justify-center bg-slate-800 text-slate-400 mb-3 shadow-inner">
                    <svg id="icon-standby" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                    <svg id="icon-allowed" class="w-10 h-10 text-emerald-400 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <svg id="icon-denied" class="w-10 h-10 text-rose-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>

                <div id="result-status-text" class="text-2xl font-black tracking-tight text-slate-300">
                    SIAP PINDAI
                </div>
                <div id="result-reason-text" class="text-xs text-slate-400 mt-1 min-h-[32px] px-2 flex items-center justify-center font-medium">
                    Arahkan kode QR tiket ke kamera atau masukkan kode
                </div>

                <!-- Indikator Verifikasi Umur PDP (Ya / Tidak Tanpa Tanggal Lahir) -->
                <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs px-2">
                    <span class="text-slate-400 font-medium">Verifikasi Umur (21+):</span>
                    <span id="badge-age-status" class="px-2.5 py-0.5 rounded-full font-bold text-[11px] bg-slate-800 text-slate-400 border border-slate-700">
                        BELUM DICEK
                    </span>
                </div>
            </div>
        </div>

        <!-- Pemilih Arah & Zona -->
        <div class="px-4 py-3 bg-slate-900/90 border-b border-slate-800">
            <div class="grid grid-cols-2 gap-2 mb-3">
                <button type="button" id="btn-dir-in" onclick="setDirection('IN')" class="py-2.5 px-3 rounded-lg text-xs font-bold uppercase tracking-wider transition-all border flex items-center justify-center space-x-1.5 bg-emerald-600 border-emerald-500 text-white shadow-md shadow-emerald-950">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    <span>MASUK (IN)</span>
                </button>
                <button type="button" id="btn-dir-out" onclick="setDirection('OUT')" class="py-2.5 px-3 rounded-lg text-xs font-bold uppercase tracking-wider transition-all border flex items-center justify-center space-x-1.5 bg-slate-800 border-slate-700 text-slate-400 hover:text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    <span>KELUAR (OUT)</span>
                </button>
            </div>

            <div class="flex items-center space-x-2">
                <label for="zone-select" class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider whitespace-nowrap">Zona:</label>
                <select id="zone-select" class="w-full bg-slate-950 border border-slate-700 rounded-lg text-xs text-slate-200 py-1.5 px-2 focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                    @forelse($zones as $z)
                        <option value="{{ $z->id }}">{{ $z->name }} ({{ $z->current_occupancy }}/{{ $z->capacity_limit }})</option>
                    @empty
                        <option value="1">Zona Utama</option>
                    @endforelse
                </select>
            </div>
        </div>

        <!-- Form Input Pindaian Manual & Simulasikan Umur -->
        <div class="p-4 flex-1 flex flex-col justify-between">
            <form id="scan-form" onsubmit="handleScanSubmit(event)" class="space-y-3">
                <div>
                    <label for="ticket-input" class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
                        Nomor Tiket / Barcode Token
                    </label>
                    <div class="relative">
                        <input type="text" id="ticket-input" name="qr_payload" placeholder="TCK-..." required autofocus
                               class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-sm font-mono text-emerald-300 placeholder-slate-600 focus:border-emerald-500 focus:outline-none tabular-nums">
                        <button type="button" onclick="document.getElementById('ticket-input').value = 'TCK-' + Math.random().toString(36).substring(2, 8).toUpperCase()"
                                class="absolute right-2 top-2 text-[10px] bg-slate-800 text-slate-400 hover:text-white px-2 py-1 rounded border border-slate-700">
                            GEN
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="age-input" class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
                            Pemeriksaan Umur Tamu
                        </label>
                        <input type="number" id="age-input" value="23" min="15" max="99" required
                               class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-slate-200 focus:border-emerald-500 focus:outline-none tabular-nums text-center">
                    </div>
                    <div>
                        <label for="nonce-input" class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
                            Nonce Anti-Replay
                        </label>
                        <input type="text" id="nonce-input" readonly
                               class="w-full bg-slate-950/60 border border-slate-800 rounded-xl px-2 py-2 text-[11px] font-mono text-slate-500 tabular-nums text-center cursor-not-allowed">
                    </div>
                </div>

                <button type="submit" id="btn-submit-scan"
                        class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-extrabold text-sm rounded-xl shadow-lg shadow-emerald-950/60 transition-all flex items-center justify-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>VALIDASI AKSES GERBANG</span>
                </button>
            </form>

            <!-- Riwayat 5 Pindaian Terakhir (Tabular Nums) -->
            <div class="mt-4 pt-3 border-t border-slate-800">
                <div class="flex items-center justify-between text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">
                    <span>Aktivitas Terakhir</span>
                    <span class="text-slate-500 text-[10px]">Real-time Log</span>
                </div>

                <div id="recent-scans-list" class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                    @forelse($recentScans as $scan)
                        <div class="text-[11px] font-mono flex items-center justify-between p-1.5 rounded-lg bg-slate-950/60 border border-slate-800/80">
                            <span class="text-slate-400 tabular-nums">{{ $scan->scanned_at ? $scan->scanned_at->format('H:i:s') : '--:--' }}</span>
                            <span class="font-bold {{ $scan->direction === 'IN' ? 'text-emerald-400' : 'text-amber-400' }}">{{ $scan->direction }}</span>
                            <span class="text-slate-300 truncate max-w-[110px]">{{ $scan->ticket?->ticket_number ?? 'TCK-UNKNOWN' }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold {{ $scan->scan_result === 'ALLOWED' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-rose-950 text-rose-400 border border-rose-800' }}">
                                {{ $scan->scan_result }}
                            </span>
                        </div>
                    @empty
                        <div class="text-center py-4 text-xs text-slate-600 italic">
                            Belum ada riwayat pemindaian pada sesi ini
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Footer Keamanan & Kriptografi PDP -->
        <footer class="bg-slate-950 px-4 py-2 border-t border-slate-800/80 flex items-center justify-between text-[10px] text-slate-500">
            <span>PDP COMPLIANT • ZERO RAW DOB</span>
            <span class="font-mono">SHA-256 HMAC CHAIN</span>
        </footer>
    </div>

    <!-- Script Logika Gerbang -->
    <script>
        let currentDirection = 'IN';

        function updateNonce() {
            const nonce = 'nc_' + Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 7);
            document.getElementById('nonce-input').value = nonce;
        }
        updateNonce();

        function setDirection(dir) {
            currentDirection = dir;
            const btnIn = document.getElementById('btn-dir-in');
            const btnOut = document.getElementById('btn-dir-out');
            if (dir === 'IN') {
                btnIn.className = 'py-2.5 px-3 rounded-lg text-xs font-bold uppercase tracking-wider transition-all border flex items-center justify-center space-x-1.5 bg-emerald-600 border-emerald-500 text-white shadow-md shadow-emerald-950';
                btnOut.className = 'py-2.5 px-3 rounded-lg text-xs font-bold uppercase tracking-wider transition-all border flex items-center justify-center space-x-1.5 bg-slate-800 border-slate-700 text-slate-400 hover:text-white';
            } else {
                btnOut.className = 'py-2.5 px-3 rounded-lg text-xs font-bold uppercase tracking-wider transition-all border flex items-center justify-center space-x-1.5 bg-amber-600 border-amber-500 text-white shadow-md shadow-amber-950';
                btnIn.className = 'py-2.5 px-3 rounded-lg text-xs font-bold uppercase tracking-wider transition-all border flex items-center justify-center space-x-1.5 bg-slate-800 border-slate-700 text-slate-400 hover:text-white';
            }
        }

        async function handleScanSubmit(e) {
            e.preventDefault();
            const ticketCode = document.getElementById('ticket-input').value.trim();
            const age = document.getElementById('age-input').value;
            const nonce = document.getElementById('nonce-input').value;
            const zoneId = document.getElementById('zone-select').value;

            const card = document.getElementById('scan-result-card');
            const statusText = document.getElementById('result-status-text');
            const reasonText = document.getElementById('result-reason-text');
            const ageBadge = document.getElementById('badge-age-status');
            const iconStandby = document.getElementById('icon-standby');
            const iconAllowed = document.getElementById('icon-allowed');
            const iconDenied = document.getElementById('icon-denied');
            const btnSubmit = document.getElementById('btn-submit-scan');

            btnSubmit.disabled = true;
            statusText.innerText = 'MEMVALIDASI...';
            statusText.className = 'text-2xl font-black tracking-tight text-amber-400 animate-pulse';

            try {
                const response = await fetch('/venue/gate/scan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        ticket_code: ticketCode,
                        patron_age: parseInt(age, 10),
                        direction: currentDirection,
                        nonce: nonce,
                        zone_id: parseInt(zoneId, 10)
                    })
                });

                const data = await response.json();
                iconStandby.classList.add('hidden');

                if (response.ok && data.status === 'ALLOWED') {
                    card.className = 'rounded-xl p-5 border transition-all duration-200 bg-emerald-950/60 border-emerald-500 shadow-xl shadow-emerald-950';
                    statusText.className = 'text-2xl font-black tracking-tight text-emerald-400';
                    statusText.innerText = 'AKSES DIIZINKAN';
                    reasonText.className = 'text-xs text-emerald-300 mt-1 min-h-[32px] px-2 flex items-center justify-center font-semibold';
                    reasonText.innerText = 'Silakan masuk ke ' + (data.zone_name || 'Venue');

                    iconAllowed.classList.remove('hidden');
                    iconDenied.classList.add('hidden');

                    ageBadge.className = 'px-2.5 py-0.5 rounded-full font-bold text-[11px] bg-emerald-900 text-emerald-200 border border-emerald-600';
                    ageBadge.innerText = 'TERVERIFIKASI (21+)';

                    showToast('Tiket ' + ticketCode + ' sah! Akses gerbang terbuka.');
                } else {
                    card.className = 'rounded-xl p-5 border transition-all duration-200 bg-rose-950/60 border-rose-500 shadow-xl shadow-rose-950';
                    statusText.className = 'text-2xl font-black tracking-tight text-rose-400';
                    statusText.innerText = 'AKSES DITOLAK';
                    reasonText.className = 'text-xs text-rose-300 mt-1 min-h-[32px] px-2 flex items-center justify-center font-semibold';
                    reasonText.innerText = data.reason || 'Tiket tidak memenuhi syarat keamanan';

                    iconDenied.classList.remove('hidden');
                    iconAllowed.classList.add('hidden');

                    if (data.age_verified === false) {
                        ageBadge.className = 'px-2.5 py-0.5 rounded-full font-bold text-[11px] bg-rose-900 text-rose-200 border border-rose-600';
                        ageBadge.innerText = 'DI BAWAH UMUR';
                    } else {
                        ageBadge.className = 'px-2.5 py-0.5 rounded-full font-bold text-[11px] bg-slate-800 text-slate-400 border border-slate-700';
                        ageBadge.innerText = 'TIDAK VALID';
                    }

                    showToast('Peringatan: ' + (data.reason || 'Akses ditolak'));
                }
            } catch (err) {
                card.className = 'rounded-xl p-5 border transition-all duration-200 bg-amber-950/60 border-amber-500';
                statusText.className = 'text-2xl font-black tracking-tight text-amber-400';
                statusText.innerText = 'GALAT JARINGAN';
                reasonText.innerText = 'Gagal menghubungi server validasi gerbang';
            } finally {
                btnSubmit.disabled = false;
                updateNonce();
            }
        }

        function showToast(text) {
            const container = document.getElementById('toast-container');
            document.getElementById('toast-text').innerText = text;
            container.classList.remove('hidden');
            setTimeout(() => {
                container.classList.add('hidden');
            }, 4000);
        }

        setInterval(() => {
            const now = new Date();
            document.getElementById('clock-display').innerText =
                String(now.getHours()).padStart(2, '0') + ':' +
                String(now.getMinutes()).padStart(2, '0') + ':' +
                String(now.getSeconds()).padStart(2, '0');
        }, 1000);
    </script>
</body>
</html>
