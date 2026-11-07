<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiket Saya — Portal Tamu Tempat Hiburan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex flex-col font-sans">
    <!-- Navbar Tamu -->
    <header class="bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center space-x-3">
            <span class="text-emerald-400 font-black text-lg tracking-wider">SUPERWEB GUEST PORTAL</span>
        </div>
        <div class="text-xs text-slate-400">
            Tamu ID: <span class="font-mono text-slate-200">#{{ $userId }}</span>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-4xl w-full mx-auto p-6 space-y-6">
        
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <h1 class="text-2xl font-black text-white tracking-tight">Dompet Tiket Elektronik Saya</h1>
            <p class="text-sm text-slate-400 mt-1">
                Tiket resmi tempat hiburan yang diamankan dengan rantai hash anti-pemalsuan dan kode QR dinamis.
            </p>
        </div>

        <!-- Daftar Tiket -->
        <div class="space-y-4">
            @forelse($tickets as $tck)
                <div class="bg-slate-900 border border-slate-800 hover:border-slate-700 transition-all rounded-2xl p-6 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="space-y-2">
                        <div class="flex items-center space-x-3">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-950 text-emerald-400 border border-emerald-800">
                                {{ $tck->status }}
                            </span>
                            <span class="font-mono text-xs text-slate-400">{{ $tck->ticket_number }}</span>
                        </div>
                        <h2 class="text-xl font-bold text-white">{{ $tck->event?->title ?? 'Acara Musik' }}</h2>
                        <div class="text-xs text-slate-400 flex items-center space-x-4">
                            <span>Tier: <strong class="text-slate-200">{{ $tck->tier?->name ?? 'General Admission' }}</strong></span>
                            <span>•</span>
                            <span>Waktu: <strong class="text-slate-200">{{ $tck->event?->doors_open_at ? $tck->event->doors_open_at->format('d M Y, H:i') : '-' }} WIB</strong></span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:items-end justify-between space-y-3">
                        <span class="font-mono font-bold text-lg text-emerald-400 tabular-nums">
                            Rp {{ number_format($tck->price_paid_idr, 0, ',', '.') }}
                        </span>
                        <a href="/guest/tickets/{{ $tck->ticket_uuid }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-950 transition-all flex items-center justify-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <span>Buka Kode Masuk QR</span>
                        </a>
                    </div>
                </div>
            @empty
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-800 flex items-center justify-center text-slate-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-300">Anda Belum Memiliki Tiket</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">
                        Tiket yang Anda beli untuk acara konser atau festival akan muncul di halaman ini.
                    </p>
                </div>
            @endforelse
        </div>
    </main>
</body>
</html>

