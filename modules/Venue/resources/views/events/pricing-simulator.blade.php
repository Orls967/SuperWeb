<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulator Harga Tier V1 — {{ $event->title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex flex-col font-sans">
    <!-- Navbar -->
    <nav class="bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center space-x-4">
            <a href="/venue/events/{{ $event->id }}" class="text-emerald-400 font-black text-xl tracking-wider hover:text-emerald-300">← {{ $event->event_code }}</a>
            <span class="text-xs bg-emerald-950 text-emerald-400 border border-emerald-800 px-2 py-0.5 rounded font-mono font-semibold">ALGORITMA V1</span>
        </div>
        <div>
            <a href="/venue/events" class="text-xs text-slate-400 hover:text-white">Daftar Acara</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-5xl w-full mx-auto p-6 space-y-6">
        
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <h1 class="text-2xl font-black text-white tracking-tight">Simulator Penetapan Harga Berjenjang V1</h1>
            <p class="text-sm text-slate-400 mt-1">
                Perhitungan harga dinamis berdasarkan hitung mundur hari (Early Bird < H-30, Presale < H-7, Door), faktor rasio permintaan sold/capacity, diskon cuaca, pembulatan ke bawah Rp 1.000, serta clamping [Floor, Ceiling].
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Controls Form -->
            <div class="lg:col-span-5 bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl space-y-4">
                <h2 class="text-sm font-bold text-slate-200 uppercase tracking-wider border-b border-slate-800 pb-2">
                    Parameter Simulasi
                </h2>

                <form method="GET" action="/venue/events/{{ $event->id }}/simulator" class="space-y-4">
                    <div>
                        <label for="base_price_idr" class="block text-xs font-semibold text-slate-400 uppercase mb-1">
                            Harga Dasar Tier (Base Price IDR)
                        </label>
                        <input type="number" step="1000" min="0" id="base_price_idr" name="base_price_idr" value="{{ $basePrice }}"
                               class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-emerald-300 tabular-nums focus:border-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label for="days_until_event" class="block text-xs font-semibold text-slate-400 uppercase mb-1">
                            Sisa Hari Menuju Hari-H (Days Until Event)
                        </label>
                        <div class="flex items-center space-x-3">
                            <input type="range" min="0" max="60" id="days_slider" value="{{ $daysUntil }}" oninput="document.getElementById('days_until_event').value = this.value"
                                   class="flex-1 accent-emerald-500">
                            <input type="number" id="days_until_event" name="days_until_event" value="{{ $daysUntil }}" oninput="document.getElementById('days_slider').value = this.value"
                                   class="w-16 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-center font-mono text-xs tabular-nums text-slate-200">
                        </div>
                        <span class="text-[10px] text-slate-500 block mt-1">> 30 hari: Early Bird (-20%), 7-30 hari: Presale (-10%), < 7 hari: Standard/Door</span>
                    </div>

                    <div>
                        <label for="demand_bps" class="block text-xs font-semibold text-slate-400 uppercase mb-1">
                            Rasio Penjualan / Permintaan (Basis Points)
                        </label>
                        <div class="flex items-center space-x-3">
                            <input type="range" min="0" max="10000" step="500" id="demand_slider" value="{{ $demandBps }}" oninput="document.getElementById('demand_bps').value = this.value"
                                   class="flex-1 accent-emerald-500">
                            <input type="number" id="demand_bps" name="demand_bps" value="{{ $demandBps }}" oninput="document.getElementById('demand_slider').value = this.value"
                                   class="w-20 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-center font-mono text-xs tabular-nums text-slate-200">
                        </div>
                        <span class="text-[10px] text-slate-500 block mt-1">≥ 8000 bps: +20%, 5000-7999 bps: +10%, < 5000 bps: 0%</span>
                    </div>

                    <div>
                        <label for="rain_probability_bps" class="block text-xs font-semibold text-slate-400 uppercase mb-1">
                            Peluang Hujan Eksternal (Rain Probability Bps)
                        </label>
                        <div class="flex items-center space-x-3">
                            <input type="range" min="0" max="10000" step="500" id="rain_slider" value="{{ $rainProbBps }}" oninput="document.getElementById('rain_probability_bps').value = this.value"
                                   class="flex-1 accent-emerald-500">
                            <input type="number" id="rain_probability_bps" name="rain_probability_bps" value="{{ $rainProbBps }}" oninput="document.getElementById('rain_slider').value = this.value"
                                   class="w-20 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-center font-mono text-xs tabular-nums text-slate-200">
                        </div>
                        <span class="text-[10px] text-slate-500 block mt-1">≥ 6000 bps (Peluang hujan tinggi): Diskon Cuaca Terbuka -10%</span>
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-950 transition-all">
                        Hitung Ulang Simulasi
                    </button>
                </form>
            </div>

            <!-- Calculation Output & Certified Proof -->
            <div class="lg:col-span-7 space-y-6">
                
                <!-- Hasil Harga Akhir -->
                <div class="bg-gradient-to-br from-slate-900 to-slate-950 border border-emerald-500/50 p-6 rounded-2xl shadow-2xl relative overflow-hidden">
                    <div class="absolute -right-8 -top-8 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl"></div>
                    
                    <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest block">
                        Rekomendasi Harga Dinamis V1
                    </span>
                    <div class="text-4xl font-black text-white tabular-nums tracking-tight mt-2 flex items-baseline space-x-2">
                        <span>Rp {{ number_format($calculation['recommended_price_idr'], 0, ',', '.') }}</span>
                        <span class="text-xs text-slate-400 font-mono">/ tiket</span>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mt-6 pt-4 border-t border-slate-800 text-xs">
                        <div>
                            <span class="text-slate-500 block text-[10px] uppercase">Floor Price</span>
                            <span class="font-mono text-slate-300 font-bold tabular-nums">
                                Rp {{ number_format($calculation['floor_price_idr'], 0, ',', '.') }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-[10px] uppercase">Harga Sebelum Clamp</span>
                            <span class="font-mono text-slate-300 font-bold tabular-nums">
                                Rp {{ number_format($calculation['raw_calculated_price_idr'], 0, ',', '.') }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-[10px] uppercase">Ceiling Price</span>
                            <span class="font-mono text-slate-300 font-bold tabular-nums">
                                Rp {{ number_format($calculation['ceiling_price_idr'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Komponen Pengganda (Multiplier Breakdown) -->
                <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl space-y-3">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">
                        Rincian Faktor Pengganda
                    </h3>

                    <div class="space-y-2 text-xs">
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/60 border border-slate-800">
                            <span class="text-slate-400">Tahap Countdown (Hari: {{ $daysUntil }})</span>
                            <span class="font-mono font-bold text-slate-200 uppercase">{{ $calculation['countdown_tier'] }}</span>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/60 border border-slate-800">
                            <span class="text-slate-400">Pengganda Permintaan (Demand: {{ $demandBps }} bps)</span>
                            <span class="font-mono font-bold {{ $calculation['demand_factor_bps'] > 0 ? 'text-emerald-400' : 'text-slate-400' }}">
                                {{ $calculation['demand_factor_bps'] > 0 ? '+' : '' }}{{ $calculation['demand_factor_bps'] / 100 }}%
                            </span>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/60 border border-slate-800">
                            <span class="text-slate-400">Peluang Hujan (Rain: {{ $rainProbBps }} bps)</span>
                            <span class="font-mono font-bold {{ $calculation['weather_factor_bps'] < 0 ? 'text-rose-400' : 'text-slate-400' }}">
                                {{ $calculation['weather_factor_bps'] / 100 }}%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Sertifikat Kriptografis V1 -->
                @if(isset($calculation['certificate']))
                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-xl space-y-2 font-mono text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-emerald-400 font-bold uppercase text-[11px] tracking-wider">
                                ✓ Sertifikat Algoritma V1 Terverifikasi
                            </span>
                            <span class="text-[10px] text-slate-500">SHA-256 KANONIK</span>
                        </div>
                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-slate-400 text-[11px] break-all select-all">
                            {{ $calculation['certificate']->signatureHash }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </main>
</body>
</html>

