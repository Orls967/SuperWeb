<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resi Tidak Ditemukan | AutoServe Logistics</title>
    <meta name="description" content="Nomor resi tidak ditemukan dalam sistem pelacakan kargo.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between selection:bg-blue-600 selection:text-white">

    <header class="border-b border-slate-800/80 bg-slate-900/50 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="{{ route('track.index') }}" class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center shadow-md shadow-blue-500/20">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                </div>
                <span class="font-bold text-base tracking-tight bg-gradient-to-r from-blue-400 to-indigo-300 bg-clip-text text-transparent">AutoServe Logistics</span>
            </a>
        </div>
    </header>

    <main class="flex-1 flex flex-col items-center justify-center px-4 py-16">
        <div class="w-full max-w-md text-center space-y-6">
            <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mx-auto shadow-lg shadow-amber-500/10">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <div class="space-y-2">
                <h1 class="text-2xl font-bold text-white">Nomor Resi Tidak Ditemukan</h1>
                <p class="text-sm text-slate-400">
                    Nomor resi <span class="font-mono font-bold text-amber-300">{{ $trackingNumber }}</span> tidak terdaftar dalam basis data jaringan logistik kami.
                </p>
            </div>

            <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-400 text-left space-y-2">
                <div class="font-semibold text-slate-300">Tips Pengecekan:</div>
                <ul class="list-disc list-inside space-y-1">
                    <li>Pastikan format nomor resi diawali dengan <code>SRX</code> (13 karakter).</li>
                    <li>Periksa kembali angka check digit atau ketikan nomor resi.</li>
                    <li>Jika baru saja dipesan beberapa saat lalu, tunggu sinkronisasi sistem.</li>
                </ul>
            </div>

            <div class="pt-2">
                <a href="{{ route('track.index') }}"
                   class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold transition shadow-md shadow-blue-600/20">
                    Coba Lacak Nomor Lain
                </a>
            </div>
        </div>
    </main>

    <footer class="border-t border-slate-800/80 bg-slate-900/30 py-6 text-center text-xs text-slate-500">
        <p>&copy; {{ date('Y') }} AutoServe Logistics. Multimodal Freight & Supply Chain Management.</p>
    </footer>

</body>
</html>
