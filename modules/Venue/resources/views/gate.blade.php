<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Turnstile Scanner - Venue Access</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex flex-col items-center p-4">
    <main class="w-full max-w-[375px] bg-gray-800 rounded-2xl shadow-xl border border-gray-700 p-5 flex flex-col space-y-4">
        <header class="border-b border-gray-700 pb-3 flex justify-between items-center">
            <h1 class="text-lg font-bold text-emerald-400 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                Turnstile Scanner
            </h1>
            <span class="text-xs bg-emerald-950 text-emerald-300 px-2 py-0.5 rounded-full border border-emerald-800">ONLINE</span>
        </header>

        <section class="relative bg-black rounded-xl overflow-hidden aspect-square flex flex-col items-center justify-center border border-gray-700">
            <div class="absolute inset-4 border-2 border-dashed border-emerald-500/50 rounded-lg pointer-events-none animate-pulse"></div>
            <p class="text-xs text-gray-400">Arahkan kamera ke QR tiket</p>
        </section>

        <form method="POST" action="/venue/ticketing/gate/scan" class="space-y-3">
            @csrf
            <div>
                <label for="qr_payload" class="block text-xs font-semibold text-gray-300 mb-1">Payload QR Tiket</label>
                <input
                    type="text"
                    id="qr_payload"
                    name="qr_payload"
                    placeholder="{uuid}.{key_id}.{hmac}"
                    required
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono"
                >
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
                <button
                    type="submit"
                    name="direction"
                    value="IN"
                    class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-2.5 px-4 rounded-lg text-sm transition-colors flex items-center justify-center gap-1 shadow-lg shadow-emerald-900/30"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    Turnstile MASUK
                </button>
                <button
                    type="submit"
                    name="direction"
                    value="OUT"
                    class="w-full bg-amber-600 hover:bg-amber-500 text-white font-semibold py-2.5 px-4 rounded-lg text-sm transition-colors flex items-center justify-center gap-1 shadow-lg shadow-amber-900/30"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Turnstile KELUAR
                </button>
            </div>
        </form>
    </main>
</body>
</html>

