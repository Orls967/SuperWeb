<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jembatan Timbang Digital — Mining Terminal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col items-center justify-start p-3 sm:p-6 font-sans antialiased">
    <!-- Viewport container optimized for 375px mobile screen -->
    <div class="w-full max-w-sm bg-slate-800 rounded-2xl shadow-2xl border border-slate-700 overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-amber-600 to-amber-500 p-4 text-slate-950 font-bold flex items-center justify-between shadow-md">
            <div class="flex items-center space-x-2">
                <span class="text-xl">⚖️</span>
                <span class="text-base tracking-wide">Jembatan Timbang</span>
            </div>
            <span class="text-xs font-mono uppercase bg-slate-950/20 px-2 py-0.5 rounded text-slate-900">Operator</span>
        </div>

        <!-- Terminal Status Bar -->
        <div class="bg-slate-950/60 px-4 py-2 border-b border-slate-700/80 flex items-center justify-between text-xs text-slate-400">
            <span>Terminal: #WB-01</span>
            <span class="flex items-center space-x-1.5">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-emerald-400 font-medium">ONLINE</span>
            </span>
        </div>

        <!-- Form Card -->
        <form method="POST" action="/mining/operations/weighbridge/ticket" class="p-4 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Nomor Plat Truk</label>
                <input type="text" name="truck_plate_number" required placeholder="DT-101-SUL"
                    class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none uppercase">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Berat Bruto (kg)</label>
                    <input type="number" name="gross_weight_kg" required placeholder="85000"
                        class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Berat Tara (kg)</label>
                    <input type="number" name="tare_weight_kg" required placeholder="35000"
                        class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <!-- Overload Warning Indicator -->
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-3 flex items-start space-x-2 text-amber-300 text-xs">
                <span class="text-sm">⚠️</span>
                <div>
                    <span class="font-bold">Overload Warning</span>: Muatan neto melebihi kapasitas truk akan otomatis ditandai dan dilaporkan ke evaluasi kontraktor.
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit"
                class="w-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold py-3 px-4 rounded-xl shadow-lg hover:shadow-amber-500/20 transition-all flex items-center justify-center space-x-2 text-sm">
                <span>Simpan Tiket Timbang</span>
                <span>➔</span>
            </button>
        </form>
    </div>
</body>
</html>
