<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event->title }} — Detail Acara Venue Operations</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex flex-col font-sans">
    <!-- Navbar -->
    <nav class="bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center space-x-4">
            <a href="/venue/events" class="text-emerald-400 font-black text-xl tracking-wider hover:text-emerald-300">← SUPERWEB // VENUE</a>
            <span class="text-xs bg-slate-800 text-slate-400 px-2 py-0.5 rounded font-mono font-semibold">{{ $event->event_code }}</span>
        </div>
        <div class="flex items-center space-x-4">
            <a href="/venue/events" class="text-xs text-slate-400 hover:text-white">Kembali ke Kalender</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-6xl w-full mx-auto p-6 space-y-6">
        
        <!-- Alerts -->
        @if(session('success'))
            <div class="bg-emerald-950/80 border border-emerald-500 text-emerald-200 px-4 py-3 rounded-xl flex items-center justify-between shadow-lg">
                <span class="text-sm font-medium">{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
            </div>
        @endif

        <!-- Event Hero & Actions -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="flex items-center space-x-3">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-950 text-emerald-400 border border-emerald-800">
                        {{ $event->status }}
                    </span>
                    <span class="text-xs font-mono text-slate-400">{{ $event->event_code }}</span>
                </div>
                <h1 class="text-3xl font-black text-white tracking-tight">{{ $event->title }}</h1>
                <p class="text-sm text-slate-400">
                    Diselenggarakan di <strong class="text-slate-200">{{ $event->venue?->name ?? 'Venue N/A' }}</strong> (Kota: {{ $event->venue?->city ?? '-' }}). Batas usia minimal: <strong class="text-amber-400">{{ $event->venue?->min_age_requirement ?? 21 }}+</strong>.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="/venue/events/{{ $event->id }}/simulator" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase tracking-wider rounded-xl border border-slate-700 transition-all flex items-center space-x-2">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <span>Simulator Harga V1</span>
                </a>
                
                @if(in_array(strtolower($event->status), ['on_sale', 'live']))
                    <form action="/venue/events/{{ $event->id }}/close" method="POST" onsubmit="return confirm('Tutup acara ini sekarang? Unearned revenue akan diakui ke akun revenue.');">
                        @csrf
                        <button type="submit" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-purple-950 transition-all flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Tutup Acara (Akui Revenue)</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Jadwal Pintu Buka</span>
                <span class="text-lg font-bold text-slate-200 tabular-nums mt-1 block">
                    {{ $event->doors_open_at ? $event->doors_open_at->format('d M Y, H:i') : '-' }} WIB
                </span>
                <span class="text-[11px] text-slate-500 mt-1 block">Tutup: {{ $event->doors_closed_at ? $event->doors_closed_at->format('H:i') : 'Belum Ditutup' }}</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Batas Harga Tier</span>
                <span class="text-lg font-bold text-slate-200 tabular-nums mt-1 block">
                    Rp {{ number_format($event->ticket_floor_price_idr, 0, ',', '.') }}
                </span>
                <span class="text-[11px] text-slate-500 mt-1 block">Ceiling: Rp {{ number_format($event->ticket_ceiling_price_idr, 0, ',', '.') }}</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Tiket Terjual (Subledger)</span>
                <span class="text-lg font-bold text-emerald-400 tabular-nums mt-1 block">
                    {{ $event->tickets->count() }} Lembar
                </span>
                <span class="text-[11px] text-slate-500 mt-1 block">
                    Total: Rp {{ number_format($event->tickets->sum('price_paid_idr'), 0, ',', '.') }}
                </span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Status Izin Keramaian</span>
                @php $activePermit = $event->permits->firstWhere('status', 'APPROVED'); @endphp
                @if($activePermit)
                    <span class="text-lg font-bold text-cyan-400 mt-1 block">TERBIT ✓</span>
                    <span class="text-[11px] text-slate-400 font-mono mt-1 block">{{ $activePermit->permit_number }}</span>
                @else
                    <span class="text-lg font-bold text-amber-400 mt-1 block">BELUM ADA</span>
                    <span class="text-[11px] text-slate-500 mt-1 block">Wajib sebelum hari-H</span>
                @endif
            </div>
        </div>

        <!-- Tier Tiket & Alokasi Kuota -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-200">Tier Tiket & Kuota Kapasitas Acara</h2>
                <span class="text-xs text-slate-500 font-mono">D89-1 • Algoritma V1 Dynamic Tier Band</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-950/60 text-slate-400 text-[11px] font-semibold uppercase tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">Nama Tier</th>
                            <th class="px-6 py-3.5">Harga Dasar (IDR)</th>
                            <th class="px-6 py-3.5 text-center">Alokasi Kapasitas</th>
                            <th class="px-6 py-3.5 text-center">Terjual</th>
                            <th class="px-6 py-3.5 text-right">Persentase Terisi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @forelse($event->tiers as $tier)
                            @php
                                $soldCount = $event->tickets->where('tier_id', $tier->id)->count();
                                $pct = $tier->allocated_capacity > 0 ? round(($soldCount / $tier->allocated_capacity) * 100, 1) : 0;
                            @endphp
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-6 py-4 font-bold text-slate-200">{{ $tier->name }}</td>
                                <td class="px-6 py-4 font-mono tabular-nums text-slate-300">
                                    Rp {{ number_format($tier->base_price_idr, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono tabular-nums text-slate-300">
                                    {{ number_format($tier->allocated_capacity, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono tabular-nums text-emerald-400 font-bold">
                                    {{ $soldCount }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono tabular-nums">
                                    <div class="flex items-center justify-end space-x-2">
                                        <div class="w-20 bg-slate-800 rounded-full h-2 overflow-hidden">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ min(100, $pct) }}%"></div>
                                        </div>
                                        <span class="text-xs text-slate-300">{{ $pct }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-xs text-slate-500 italic">
                                    Belum ada tier tiket yang dikonfigurasi untuk acara ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Izin Keramaian Terkait -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-200">Surat Izin Keramaian (Police & Regulatory Compliance)</h2>
                <a href="/venue/permits" class="text-xs text-emerald-400 hover:text-emerald-300 font-medium">Buka Menu Izin →</a>
            </div>

            <div class="p-6">
                @if($event->permits->isEmpty())
                    <div class="text-center py-6 text-xs text-slate-500 italic">
                        Belum ada dokumen izin keramaian yang diterbitkan untuk acara ini.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($event->permits as $permit)
                            <div class="p-4 bg-slate-950/60 border border-slate-800 rounded-xl space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-bold text-cyan-400">{{ $permit->permit_number }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $permit->status === 'APPROVED' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-amber-950 text-amber-400 border border-amber-800' }}">
                                        {{ $permit->status }}
                                    </span>
                                </div>
                                <div class="text-xs text-slate-300 font-semibold">{{ $permit->issuing_authority }}</div>
                                <div class="text-[11px] text-slate-400 flex items-center justify-between">
                                    <span>Kapasitas Disetujui: {{ number_format($permit->authorized_capacity, 0, ',', '.') }} orang</span>
                                    <span>Jam Malam: {{ $permit->approved_noise_curfew ?? '-' }} WIB</span>
                                </div>
                                <div class="text-[10px] text-slate-500 font-mono truncate">
                                    Hash: {{ $permit->permit_hash }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </main>
</body>
</html>

