<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lacak Pengiriman Kargo | AutoServe Logistics</title>
    <meta name="description" content="Lacak status pengiriman kargo multimoda darat, laut, dan udara secara real-time.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between selection:bg-blue-600 selection:text-white">

    {{-- Header --}}
    <header class="border-b border-slate-800/80 bg-slate-900/50 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-3 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-blue-500/25 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                </div>
                <div class="leading-tight">
                    <span class="font-bold text-lg tracking-tight bg-gradient-to-r from-blue-400 to-indigo-300 bg-clip-text text-transparent">AutoServe Logistics</span>
                    <span class="block text-[10px] text-slate-400 tracking-wider uppercase font-semibold">Pelacakan Kargo Multimoda</span>
                </div>
            </a>

            <div class="flex items-center gap-3">
                <a href="{{ route('login') }}" class="text-sm font-medium text-slate-300 hover:text-white px-3 py-1.5 transition">Masuk Portal Shipper</a>
            </div>
        </div>
    </header>

    {{-- Main Container --}}
    <main class="flex-1 flex flex-col items-center justify-center px-4 py-16">
        <div class="w-full max-w-2xl text-center space-y-8">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-semibold tracking-wide">
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
                    Jaringan Kalimantan & Multimoda Nasional
                </div>
                <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-white">
                    Lacak Pengiriman Kargo
                </h1>
                <p class="text-slate-400 text-sm sm:text-base max-w-lg mx-auto leading-relaxed">
                    Pantau posisi kargo Anda secara akurat dari titik asal, hub transit, hingga tiba di tujuan penerima.
                </p>
            </div>

            {{-- Search Box --}}
            <form action="{{ route('track.index') }}" method="GET" class="relative group">
                <div class="relative flex items-center rounded-2xl bg-slate-900 border border-slate-700/80 shadow-2xl shadow-blue-900/10 group-focus-within:border-blue-500 group-focus-within:ring-4 group-focus-within:ring-blue-500/20 transition-all p-2">
                    <div class="pl-3 pr-2 text-slate-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text"
                           name="q"
                           required
                           autofocus
                           placeholder="Masukkan Nomor Resi (misal: SRX1234567890)"
                           class="w-full bg-transparent border-none text-white text-base sm:text-lg focus:outline-none placeholder-slate-500 px-2 py-2 font-mono uppercase">
                    <button type="submit"
                            class="px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold text-sm sm:text-base hover:from-blue-500 hover:to-indigo-500 transition-all shadow-md shadow-blue-600/30 shrink-0">
                        Lacak Kargo
                    </button>
                </div>
            </form>

            <div class="flex items-center justify-center gap-6 text-xs text-slate-400">
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>Privasi PII Terlindungi</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Pembaruan Real-Time</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Multi-Mode Transit</span>
                </div>
            </div>
        </div>
    </main>

    {{-- Footer --}}
    <footer class="border-t border-slate-800/80 bg-slate-900/30 py-6 text-center text-xs text-slate-500">
        <p>&copy; {{ date('Y') }} AutoServe Logistics. Multimodal Freight & Supply Chain Management.</p>
    </footer>

</body>
</html>
