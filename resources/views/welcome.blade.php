<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AutoServe - Sistem Manajemen Bengkel Otomotif Terpadu</title>
    <meta name="description" content="AutoServe - Sistem manajemen bengkel otomotif terpadu untuk booking servis, manajemen sparepart, dan invoice otomatis.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-text {
            background: linear-gradient(135deg, #60a5fa, #a78bfa, #f472b6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .glow {
            box-shadow: 0 0 60px rgba(96, 165, 250, 0.15), 0 0 120px rgba(167, 139, 250, 0.1);
        }
    </style>
</head>
<body class="bg-slate-950 text-white min-h-screen overflow-x-hidden">

    {{-- Animated Background --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-1/4 -left-40 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl float-animation"></div>
        <div class="absolute bottom-1/4 -right-40 w-96 h-96 bg-violet-500/10 rounded-full blur-3xl float-animation" style="animation-delay: -3s;"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-gradient-to-r from-blue-500/5 to-violet-500/5 rounded-full blur-3xl"></div>
    </div>

    {{-- Navbar --}}
    <nav class="relative z-10 flex items-center justify-between max-w-6xl mx-auto px-6 py-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center shadow-lg shadow-blue-500/25">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
            </div>
            <span class="text-xl font-bold gradient-text">AutoServe</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('login') }}" class="px-5 py-2.5 text-sm font-medium text-slate-300 hover:text-white transition-colors">Login</a>
            <a href="{{ route('register') }}" class="px-5 py-2.5 rounded-xl text-sm font-medium bg-gradient-to-r from-blue-500 to-violet-600 text-white hover:from-blue-600 hover:to-violet-700 transition-all shadow-lg shadow-blue-500/25">Daftar</a>
        </div>
    </nav>

    {{-- Hero Section --}}
    <section class="relative z-10 max-w-6xl mx-auto px-6 pt-20 pb-32 text-center">
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-medium mb-8">
            <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
            Sistem Bengkel Modern
        </div>

        <h1 class="text-5xl sm:text-7xl font-black leading-tight max-w-4xl mx-auto">
            Manajemen Bengkel
            <span class="gradient-text">Otomotif Terpadu</span>
        </h1>

        <p class="text-lg text-slate-400 mt-6 max-w-2xl mx-auto leading-relaxed">
            Dari booking servis, job order mekanik, pemakaian sparepart, hingga invoice otomatis — semua terintegrasi dalam satu sistem.
        </p>

        <div class="flex items-center justify-center gap-4 mt-10">
            <a href="{{ route('register') }}" class="px-8 py-4 rounded-2xl text-base font-bold bg-gradient-to-r from-blue-500 to-violet-600 text-white hover:from-blue-600 hover:to-violet-700 transition-all shadow-2xl shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5">
                Mulai Sekarang →
            </a>
            <a href="{{ route('login') }}" class="px-8 py-4 rounded-2xl text-base font-medium text-slate-300 border border-slate-700 hover:border-slate-600 hover:bg-slate-800/50 transition-all">
                Login
            </a>
        </div>

        {{-- Feature Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-24 text-left">
            @php
                $features = [
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>', 'title' => 'Online Booking', 'desc' => 'Pelanggan buat jadwal servis kapanpun dari mana saja', 'color' => 'blue'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>', 'title' => 'Job Order', 'desc' => 'Track status perbaikan real-time: Pending → In Progress → Done', 'color' => 'violet'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>', 'title' => 'Stok Otomatis', 'desc' => 'Stok sparepart terpotong otomatis saat job selesai', 'color' => 'emerald'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>', 'title' => 'Invoice Instan', 'desc' => 'Grand total dihitung otomatis, cetak invoice satu klik', 'color' => 'pink'],
                ];
            @endphp

            @foreach($features as $f)
            <div class="p-6 rounded-2xl bg-white/[0.03] border border-white/[0.06] hover:border-{{ $f['color'] }}-500/30 hover:bg-{{ $f['color'] }}-500/5 transition-all group">
                <div class="w-10 h-10 rounded-xl bg-{{ $f['color'] }}-500/10 flex items-center justify-center mb-4 group-hover:bg-{{ $f['color'] }}-500/20 transition-colors">
                    <svg class="w-5 h-5 text-{{ $f['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $f['icon'] !!}</svg>
                </div>
                <h3 class="font-bold text-white mb-1">{{ $f['title'] }}</h3>
                <p class="text-sm text-slate-400 leading-relaxed">{{ $f['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </section>

    {{-- Footer --}}
    <footer class="relative z-10 border-t border-slate-800 py-8 text-center">
        <p class="text-sm text-slate-500">© {{ date('Y') }} AutoServe. Sistem Manajemen Bengkel Otomotif Terpadu.</p>
    </footer>
</body>
</html>
