<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftarkan Acara Baru — Venue Operations</title>
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
            <span class="text-xs bg-slate-800 text-slate-400 px-2 py-0.5 rounded font-mono font-semibold">FORM ACARA</span>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-3xl w-full mx-auto p-6 space-y-6">
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <h1 class="text-2xl font-black text-white tracking-tight">Pendaftaran Acara & Konfigurasi Tiket</h1>
            <p class="text-sm text-slate-400 mt-1">
                Lengkapi rincian acara, pilihan tempat hiburan, rentang harga lantai & plafon, serta waktu pembukaan gerbang.
            </p>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <form action="/venue/events" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Pilih Venue / Tempat Hiburan</label>
                    <select name="venue_id" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-slate-200">
                        @foreach($venues as $vn)
                            <option value="{{ $vn->id }}">{{ $vn->name }} ({{ $vn->city }} - Min Usia: {{ $vn->min_age_requirement }}+)</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Kode Unik Acara</label>
                        <input type="text" name="event_code" value="EVT-{{ strtoupper(bin2hex(random_bytes(3))) }}" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-emerald-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Judul / Nama Acara</label>
                        <input type="text" name="title" placeholder="Sunset Beach Festival 2026" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Waktu Pintu Buka (Doors Open)</label>
                        <input type="datetime-local" name="doors_open_at" value="{{ date('Y-m-d\TH:i', strtotime('+7 days 18:00')) }}" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Waktu Selesai (Opsional)</label>
                        <input type="datetime-local" name="doors_close_at" value="{{ date('Y-m-d\TH:i', strtotime('+7 days 23:59')) }}" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Floor Price (IDR)</label>
                        <input type="number" name="ticket_floor_price_idr" value="250000" step="10000" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Current Price (IDR)</label>
                        <input type="number" name="current_ticket_price_idr" value="350000" step="10000" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-emerald-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Ceiling Price (IDR)</label>
                        <input type="number" name="ticket_ceiling_price_idr" value="750000" step="10000" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-slate-200">
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-end space-x-3">
                    <a href="/venue/events" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-bold rounded-xl hover:bg-slate-700">Batal</a>
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-950">
                        Simpan & Daftarkan Event
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>

