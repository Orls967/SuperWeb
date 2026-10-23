@extends('layouts.app')

@section('content')
<div class="py-8 bg-slate-950 min-h-screen text-slate-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-slate-900 via-violet-950 to-slate-900 p-6 rounded-2xl border border-violet-900/40 shadow-2xl flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-violet-500/10 text-violet-400 border border-violet-500/20">
                        ● Smart Access On-Demand
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        Fase 78 Flex-Space & Co-Working
                    </span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-2">Flex-Space & Co-Working Operations</h1>
                <p class="text-xs text-slate-400 mt-1">Booking Engine Anti-Overlap, Deposit Hold Escrow, Smart Access Check-In dengan Paspor Kriptografis, dan Utilitas Sesi Real-Time.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('proptech.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-lg border border-slate-700 transition">
                    ← Smart Building IoT
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-950/60 border border-emerald-500/40 rounded-xl text-emerald-200 text-sm flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <span class="text-xs text-emerald-400 font-mono">OK</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 bg-rose-950/60 border border-rose-500/40 rounded-xl text-rose-200 text-sm flex items-center justify-between">
                <span>{{ session('error') }}</span>
                <span class="text-xs text-rose-400 font-mono">ERR</span>
            </div>
        @endif

        <!-- KPI Metrics Ribbon -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Flex Space & Pods</div>
                <div class="mt-2 text-2xl font-bold text-white">{{ $totalSpaces }} <span class="text-xs font-normal text-slate-500">unit</span></div>
                <div class="mt-1 text-xs text-violet-400">Terkoneksi Telemetri Fase 76</div>
            </div>

            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Booking Aktif</div>
                <div class="mt-2 text-2xl font-bold text-cyan-400">{{ $activeBookingsCount }} <span class="text-xs font-normal text-slate-500">sesi</span></div>
                <div class="mt-1 text-xs text-slate-400">Lock Rentang Waktu Anti-Overlap</div>
            </div>

            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Pendapatan Sewa Captured</div>
                <div class="mt-2 text-2xl font-bold text-emerald-400">Rp {{ number_format($totalRevenueIdr, 0, ',', '.') }}</div>
                <div class="mt-1 text-xs text-slate-400">Settled di prp:flex_revenue:IDR</div>
            </div>

            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Escrow Deposit Held</div>
                <div class="mt-2 text-2xl font-bold text-amber-400">Rp {{ number_format($totalDepositHeldIdr, 0, ',', '.') }}</div>
                <div class="mt-1 text-xs text-slate-400">30% jaminan anti no-show</div>
            </div>
        </div>

        <!-- Main Workspace: Spaces Inventory & Booking Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left 2 Cols: Space Inventory & Booking Table -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Flex Spaces Cards Grid -->
                <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-white mb-4">Inventori Ruang & Pod On-Demand</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @forelse($spaces as $space)
                            <div class="p-4 bg-slate-950/80 rounded-xl border border-slate-800 hover:border-violet-600/50 transition flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-300">{{ $space->space_code }}</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-violet-950 text-violet-300 border border-violet-800">{{ $space->space_type }}</span>
                                    </div>
                                    <h3 class="text-white font-semibold text-sm mt-2">{{ $space->name }}</h3>
                                    <p class="text-xs text-slate-400 mt-1">
                                        Zona: <span class="text-slate-200">{{ $space->zone?->name ?? 'SCBD Lounge' }}</span> • 
                                        Kapasitas: <span class="text-slate-200">{{ $space->capacity_persons }} org</span>
                                    </p>
                                </div>
                                <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between">
                                    <span class="text-sm font-bold text-emerald-400 font-mono">Rp {{ number_format($space->rate_per_hour_idr, 0, ',', '.') }} <span class="text-[10px] font-normal text-slate-400">/jam</span></span>
                                    <span class="text-xs font-semibold text-slate-300">{{ $space->status }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-slate-500 text-sm col-span-2">Belum ada ruang flex-space terdaftar.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Bookings History & Operations Table -->
                <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-white mb-4">Daftar Reservasi & Kontrol Sesi</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950 text-slate-400 uppercase font-mono text-[10px]">
                                <tr>
                                    <th class="p-3">Kode Booking</th>
                                    <th class="p-3">Ruang</th>
                                    <th class="p-3">Waktu Sesi</th>
                                    <th class="p-3 text-right">Deposit</th>
                                    <th class="p-3 text-right">Total</th>
                                    <th class="p-3 text-center">Status</th>
                                    <th class="p-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800 font-mono">
                                @forelse($bookings as $b)
                                    <tr class="hover:bg-slate-800/40 transition">
                                        <td class="p-3 font-semibold text-white">{{ $b->booking_code }}</td>
                                        <td class="p-3 text-slate-300">{{ $b->space?->name ?? 'Space' }}</td>
                                        <td class="p-3 text-[11px] text-slate-400">
                                            {{ $b->start_time->format('d M H:i') }} - {{ $b->end_time->format('H:i') }}
                                        </td>
                                        <td class="p-3 text-right text-amber-400">Rp {{ number_format($b->deposit_amount_idr, 0, ',', '.') }}</td>
                                        <td class="p-3 text-right font-bold text-emerald-400">Rp {{ number_format($b->total_price_idr, 0, ',', '.') }}</td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold 
                                                @if($b->status === 'CHECKED_IN') bg-emerald-950 text-emerald-300 border border-emerald-800
                                                @elseif($b->status === 'NO_SHOW') bg-rose-950 text-rose-300 border border-rose-800
                                                @else bg-violet-950 text-violet-300 border border-violet-800
                                                @endif">
                                                {{ $b->status }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-center">
                                            @if($b->status === 'HELD' || $b->status === 'CONFIRMED')
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <form action="{{ route('proptech.flex.checkin', $b) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-semibold rounded shadow">
                                                            Check-in
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('proptech.flex.noshow', $b) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="px-2 py-1 bg-rose-600 hover:bg-rose-500 text-white text-[10px] font-semibold rounded shadow">
                                                            No-Show
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-slate-500 text-[10px]">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-slate-500">Belum ada booking flex-space.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Right Col: Booking Form & Rules -->
            <div class="space-y-6">
                
                <!-- Instant Booking Form -->
                <div class="bg-slate-900/90 rounded-2xl border border-violet-900/50 p-6 shadow-xl">
                    <h2 class="text-base font-semibold text-white mb-2">Reservasi Slot On-Demand</h2>
                    <p class="text-xs text-slate-400 mb-4">Pilih ruang, tentukan durasi, dan sistem akan mengunci slot serta menahan deposit secara otomatis.</p>
                    
                    <form action="{{ route('proptech.flex.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="text-xs text-slate-300">Pilih Ruang / Pod:</label>
                            <select name="flex_space_id" required class="w-full mt-1 bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-violet-500">
                                @foreach($spaces as $space)
                                    <option value="{{ $space->id }}">{{ $space->name }} (Rp {{ number_format($space->rate_per_hour_idr, 0, ',', '.') }}/jam)</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-slate-300">Waktu Mulai:</label>
                            <input type="datetime-local" name="start_time" required value="{{ now()->addHour()->format('Y-m-d\TH:00') }}" class="w-full mt-1 bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-violet-500 font-mono">
                        </div>
                        <div>
                            <label class="text-xs text-slate-300">Waktu Selesai:</label>
                            <input type="datetime-local" name="end_time" required value="{{ now()->addHours(3)->format('Y-m-d\TH:00') }}" class="w-full mt-1 bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-violet-500 font-mono">
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="w-full py-2 px-4 bg-violet-600 hover:bg-violet-500 text-white text-xs font-semibold rounded-lg transition shadow flex items-center justify-center gap-1.5">
                                <span>🔒 Kunci Slot & Tahan Deposit (30%)</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Smart Access Rules Card -->
                <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 shadow-xl space-y-3">
                    <h2 class="text-base font-semibold text-white">Smart Access & Aturan Operasional</h2>
                    <ul class="text-xs text-slate-400 space-y-2 leading-relaxed">
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400">✓</span>
                            <span><strong>Anti-Overlap:</strong> Rentang waktu yang bertabrakan bahkan 1 menit otomatis ditolak oleh lock database.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400">✓</span>
                            <span><strong>Cryptographic Passport:</strong> Check-in menggunakan scan QR berbasis tanda tangan kriptografis untuk membuka turnstile.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400">✓</span>
                            <span><strong>No-Show Penalty:</strong> Deposit 30% hangus menjadi denda jika pengguna tidak hadir pada jadwal sesi.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400">✓</span>
                            <span><strong>Integrasi Utilitas:</strong> Sesi aktif otomatis menambah beban konsumsi daya ke zona gedung terkait.</span>
                        </li>
                    </ul>
                </div>

            </div>

        </div>

    </div>
</div>
@endsection
