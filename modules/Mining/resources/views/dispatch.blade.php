<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Dispatch Console — Mining Operations</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-6 font-sans antialiased">
    <div class="max-w-4xl mx-auto bg-slate-800 rounded-2xl shadow-2xl border border-slate-700 p-6 space-y-6">
        <div class="flex items-center justify-between border-b border-slate-700 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                    <span>🚛</span> Fleet Haul Dispatch Console
                </h1>
                <p class="text-sm text-slate-400">Optimal truck-to-shovel assignment with Hungarian dual potential certificates (PR2).</p>
            </div>
            <span class="px-3 py-1 bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-mono rounded-full">
                HUNGARIAN SOLVER ACTIVE
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-700">
                <h2 class="text-sm font-semibold text-slate-300 mb-2">Active Shovels &amp; Slots</h2>
                <p class="text-xs text-slate-400">Shovels on stopped safety areas are automatically excluded with infinite cost penalty.</p>
            </div>
            <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-700">
                <h2 class="text-sm font-semibold text-slate-300 mb-2">Available Haul Trucks</h2>
                <p class="text-xs text-slate-400">Grounded trucks and operators with expired PTW permits are strictly barred from assignment.</p>
            </div>
        </div>
    </div>
</body>
</html>
