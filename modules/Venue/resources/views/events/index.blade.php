<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Acara & Kalender Hiburan — Venue Operations</title>
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
            <span class="text-xs bg-emerald-950 text-emerald-400 border border-emerald-800 px-2 py-0.5 rounded font-mono font-semibold">FASE 89</span>
        </div>
        <div class="flex items-center space-x-6 text-sm">
            <a href="/venue/events" class="text-emerald-400 font-bold border-b-2 border-emerald-400 pb-1">Acara & Tiket</a>
            <a href="/venue/gate" class="text-slate-400 hover:text-white transition-colors">Layar Gerbang 375px</a>
            <a href="/venue/zones" class="text-slate-400 hover:text-white transition-colors">Peta Kerumunan</a>
            <a href="/venue/tables" class="text-slate-400 hover:text-white transition-colors">Meja VIP</a>
            <a href="/venue/permits" class="text-slate-400 hover:text-white transition-colors">Izin Keramaian</a>
            <a href="/venue/reports" class="text-slate-400 hover:text-white transition-colors">Laporan & Audit</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-6 space-y-6">
        
        <!-- Flash Alert / Success Toast (State 5) -->
        @if(session('success'))
            <div class="bg-emerald-950/80 border border-emerald-500 text-emerald-200 px-4 py-3 rounded-xl flex items-center justify-between shadow-lg">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-rose-950/80 border border-rose-500 text-rose-200 px-4 py-3 rounded-xl flex items-center justify-between shadow-lg">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-rose-400 hover:text-white">&times;</button>
            </div>
        @endif

        <!-- Header Section with Create Action -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <div>
                <h1 class="text-2xl font-black text-slate-100 tracking-tight">Kalender Acara & Status Penjualan Tiket</h1>
                <p class="text-sm text-slate-400 mt-1">
                    Pengawasan siklus hidup event, izin keramaian kepolisian, penetapan harga berjenjang V1, dan pengakuan akrual.
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="/venue/events/create" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-950 transition-all flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Daftarkan Event Baru</span>
                </a>
            </div>
        </div>

        <!-- Filter & Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Total Event Terdaftar</span>
                <span class="text-2xl font-black text-slate-100 tabular-nums mt-1 block">{{ $events->total() }}</span>
                <span class="text-[11px] text-emerald-400 font-medium mt-1 inline-block">Sistem multi-venue aktif</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Status Penjualan Aktif</span>
                <span class="text-2xl font-black text-emerald-400 tabular-nums mt-1 block">
                    {{ $events->filter(fn($e) => in_array($e->status, ['on_sale', 'ON_SALE']))->count() }}
                </span>
                <span class="text-[11px] text-slate-400 mt-1 inline-block">Live on-sale ticketing</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Izin Keramaian Disetujui</span>
                <span class="text-2xl font-black text-cyan-400 tabular-nums mt-1 block">
                    {{ $events->filter(fn($e) => $e->permits->where('status', 'APPROVED')->count() > 0)->count() }}
                </span>
                <span class="text-[11px] text-slate-400 mt-1 inline-block">Kepatuhan kepolisian & pemda</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Event Selesai / Ditutup</span>
                <span class="text-2xl font-black text-purple-400 tabular-nums mt-1 block">
                    {{ $events->filter(fn($e) => in_array($e->status, ['closed', 'CLOSED', 'settled', 'SETTLED']))->count() }}
                </span>
                <span class="text-[11px] text-purple-400 mt-1 inline-block">Pendapatan tiket diakui</span>
            </div>
        </div>

        <!-- Table Listing Container -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-200">Daftar Jadwal Acara</h2>
                <span class="text-xs text-slate-500 font-mono">D89-1 • Akrual Unearned Revenue</span>
            </div>

            <!-- State 1: Empty State Check -->
            @if($events->isEmpty())
                <div class="py-16 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-800 flex items-center justify-center text-slate-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-300">Belum Ada Acara Terdaftar</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">Mulai dengan mendaftarkan acara hiburan baru untuk mengatur kuota tier dan pengawasan gerbang.</p>
                    <a href="/venue/events/create" class="inline-block mt-2 px-3 py-1.5 bg-emerald-600 text-white font-bold text-xs rounded-lg hover:bg-emerald-500">Buat Event Pertama</a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-950/60 text-slate-400 text-[11px] font-semibold uppercase tracking-wider border-b border-slate-800">
                            <tr>
                                <th class="px-6 py-3.5">Kode & Judul Acara</th>
                                <th class="px-6 py-3.5">Venue & Jadwal Pintu Buka</th>
                                <th class="px-6 py-3.5 text-center">Izin Keramaian</th>
                                <th class="px-6 py-3.5 text-right">Rentang Harga (IDR)</th>
                                <th class="px-6 py-3.5 text-center">Status SM</th>
                                <th class="px-6 py-3.5 text-right">Aksi Operasional</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @foreach($events as $event)
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-100">{{ $event->title }}</div>
                                        <div class="text-xs font-mono text-emerald-400 mt-0.5">{{ $event->event_code }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-slate-300 font-medium">{{ $event->venue?->name ?? 'Venue N/A' }}</div>
                                        <div class="text-xs text-slate-500 tabular-nums">
                                            {{ $event->doors_open_at ? $event->doors_open_at->format('d M Y, H:i') : '-' }} WIB
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @php
                                            $approvedPermit = $event->permits->firstWhere('status', 'APPROVED');
                                        @endphp
                                        @if($approvedPermit)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-800">
                                                POLRI ✓ {{ $approvedPermit->permit_number }}
                                            </span>
                                        @elseif($event->permits->isNotEmpty())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-950 text-amber-400 border border-amber-800">
                                                MENUNGGU IZIN
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-800 text-slate-400 border border-slate-700">
                                                TANPA IZIN
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono text-xs tabular-nums text-slate-300">
                                        Rp {{ number_format($event->ticket_floor_price_idr, 0, ',', '.') }} –
                                        Rp {{ number_format($event->ticket_ceiling_price_idr, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @php
                                            $statusBadge = match(strtolower($event->status)) {
                                                'on_sale' => 'bg-emerald-950 text-emerald-400 border-emerald-800',
                                                'sold_out' => 'bg-blue-950 text-blue-400 border-blue-800',
                                                'live' => 'bg-rose-950 text-rose-400 border-rose-800 animate-pulse',
                                                'closed' => 'bg-purple-950 text-purple-400 border-purple-800',
                                                'settled' => 'bg-slate-800 text-slate-300 border-slate-700',
                                                'permit_pending' => 'bg-amber-950 text-amber-400 border-amber-800',
                                                default => 'bg-slate-800 text-slate-400 border-slate-700',
                                            };
                                        @endphp
                                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $statusBadge }}">
                                            {{ $event->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <a href="/venue/events/{{ $event->id }}/simulator" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700">
                                            Simulasi V1
                                        </a>
                                        <a href="/venue/events/{{ $event->id }}" class="px-2.5 py-1 bg-emerald-950 hover:bg-emerald-900 text-emerald-300 text-xs font-semibold rounded-lg border border-emerald-800">
                                            Detail
                                        </a>
                                        @if(in_array(strtolower($event->status), ['on_sale', 'live']))
                                            <form action="/venue/events/{{ $event->id }}/close" method="POST" class="inline" onsubmit="return confirm('Tutup acara ini sekarang? Unearned revenue akan diakui menjadi pendapatan.');">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 bg-purple-950 hover:bg-purple-900 text-purple-300 text-xs font-semibold rounded-lg border border-purple-800">
                                                    Tutup Acara
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Links -->
                <div class="px-6 py-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                    <div>
                        Menampilkan <span class="font-bold text-slate-200">{{ $events->firstItem() ?? 0 }}</span> sampai
                        <span class="font-bold text-slate-200">{{ $events->lastItem() ?? 0 }}</span> dari
                        <span class="font-bold text-slate-200">{{ $events->total() }}</span> event
                    </div>
                    <div>
                        {{ $events->links() }}
                    </div>
                </div>
            @endif
        </div>
    </main>
</body>
</html>

