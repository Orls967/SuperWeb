<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Papan Meja VIP & Bottle Service — Venue Operations</title>
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
            <span class="text-xs bg-amber-950 text-amber-400 border border-amber-800 px-2 py-0.5 rounded font-mono font-semibold">VIP TABLES</span>
        </div>
        <div class="flex items-center space-x-6 text-sm">
            <a href="/venue/events" class="text-slate-400 hover:text-white transition-colors">Acara & Tiket</a>
            <a href="/venue/gate" class="text-slate-400 hover:text-white transition-colors">Layar Gerbang 375px</a>
            <a href="/venue/zones" class="text-slate-400 hover:text-white transition-colors">Peta Kerumunan</a>
            <a href="/venue/tables" class="text-emerald-400 font-bold border-b-2 border-emerald-400 pb-1">Meja VIP</a>
            <a href="/venue/permits" class="text-slate-400 hover:text-white transition-colors">Izin Keramaian</a>
            <a href="/venue/reports" class="text-slate-400 hover:text-white transition-colors">Laporan & Audit</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-6 space-y-6">
        
        <!-- Alerts -->
        @if(session('success'))
            <div class="bg-emerald-950/80 border border-emerald-500 text-emerald-200 px-4 py-3 rounded-xl flex items-center justify-between shadow-lg">
                <span class="text-sm font-medium">{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
            </div>
        @endif

        <!-- Header -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-100 tracking-tight">Papan Denah Meja VIP Cabana & Daybed (V4)</h1>
                <p class="text-sm text-slate-400 mt-1">
                    Escrow deposit meja VIP Cabana, rekonsiliasi botol konsumsi aktual, denda no-show 50%, dan pengakuan pendapatan layanan botol.
                </p>
            </div>
            <button onclick="document.getElementById('modal-reserve').classList.remove('hidden')" class="px-4 py-2.5 bg-amber-600 hover:bg-amber-500 text-slate-950 font-black text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-amber-950 transition-all flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Reservasi Meja VIP Baru</span>
            </button>
        </div>

        <!-- Denah Meja Grid -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl space-y-4">
            <h2 class="text-base font-bold text-slate-200">Denah Visual Meja VIP</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                @forelse($tables as $tbl)
                    @php
                        $colorClass = match(strtolower($tbl['status'])) {
                            'reserved' => 'bg-amber-950/80 border-amber-600 text-amber-200',
                            'occupied' => 'bg-purple-950/80 border-purple-600 text-purple-200',
                            'held' => 'bg-blue-950/80 border-blue-600 text-blue-200',
                            'no_show' => 'bg-rose-950/80 border-rose-600 text-rose-200',
                            default => 'bg-slate-950/80 border-slate-700 text-slate-300',
                        };
                    @endphp
                    <div class="p-3 rounded-xl border {{ $colorClass }} flex flex-col justify-between h-28 relative group">
                        <div class="flex items-center justify-between">
                            <span class="font-black text-sm">#{{ $tbl['table_number'] }}</span>
                            <span class="text-[9px] font-mono px-1.5 py-0.5 rounded uppercase font-bold bg-black/40">
                                {{ $tbl['status'] }}
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block">VIP Deck</span>
                            <span class="text-xs font-mono font-bold tabular-nums block mt-0.5">
                                Rp {{ number_format($tbl['minimum_spend_idr'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-8 text-center text-xs text-slate-500 italic">
                        Belum ada konfigurasi denah meja VIP.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Tabel Riwayat Booking & Penyelesaian Minimum Spend -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-200">Daftar Reservasi & Tindakan Penyelesaian Bill (V4)</h2>
                <span class="text-xs text-slate-500 font-mono">D89-3 • MinimumSpendSettlement</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-950/60 text-slate-400 text-[11px] font-semibold uppercase tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">Kode Reservasi</th>
                            <th class="px-6 py-3.5">Tamu & Meja</th>
                            <th class="px-6 py-3.5">Acara</th>
                            <th class="px-6 py-3.5 text-right">Deposit Held</th>
                            <th class="px-6 py-3.5 text-center">Status</th>
                            <th class="px-6 py-3.5 text-right">Aksi Operasional</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @forelse($bookings as $bk)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-6 py-4 font-mono font-bold text-xs text-amber-400">
                                    {{ $bk->booking_code }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-100">{{ $bk->customer_name }}</div>
                                    <div class="text-xs text-slate-400">Meja #{{ $bk->table_number }} (Min: Rp {{ number_format($bk->minimum_spend_idr, 0, ',', '.') }})</div>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-300">
                                    {{ $bk->event?->title ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-xs tabular-nums text-slate-200 font-bold">
                                    Rp {{ number_format($bk->deposit_amount_idr, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border bg-slate-800 text-slate-300 border-slate-700">
                                        {{ $bk->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    @if(in_array(strtolower($bk->status), ['held', 'reserved', 'active', 'occupied']))
                                        <!-- Modal Trigger Settle -->
                                        <button onclick="openSettleModal({{ $bk->id }}, '{{ $bk->booking_code }}', {{ $bk->deposit_amount_idr }})" class="px-2.5 py-1 bg-emerald-950 hover:bg-emerald-900 text-emerald-300 text-xs font-semibold rounded-lg border border-emerald-800">
                                            Settle Bill
                                        </button>
                                        <!-- No-Show Forfeit -->
                                        <form action="/venue/tables/{{ $bk->id }}/forfeit" method="POST" class="inline" onsubmit="return confirm('Forfeit deposit meja ini 50% karena tamu no-show?');">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 bg-rose-950 hover:bg-rose-900 text-rose-300 text-xs font-semibold rounded-lg border border-rose-800">
                                                No-Show (50%)
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-500 font-mono">SELESAI</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-xs text-slate-500 italic">
                                    Belum ada transaksi reservasi meja VIP yang tercatat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Reservasi Meja -->
        <div id="modal-reserve" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 max-w-md w-full rounded-2xl p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-100">Reservasi Meja VIP</h3>
                    <button onclick="document.getElementById('modal-reserve').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
                </div>
                <form action="/venue/tables/reserve" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Nomor Meja</label>
                        <input type="text" name="table_number" value="V01" required placeholder="Contoh: V01" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Minimum Spend (IDR)</label>
                        <input type="number" name="minimum_spend_idr" value="5000000" step="100000" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-emerald-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Pilih Acara</label>
                        <select name="event_id" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                            @foreach($events as $ev)
                                <option value="{{ $ev->id }}">{{ $ev->title }} ({{ $ev->event_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Nama Pemesan Tamu</label>
                        <input type="text" name="customer_name" required placeholder="Nama Lengkap" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Nominal Deposit Ditahan (IDR)</label>
                        <input type="number" name="deposit_amount_idr" value="2000000" step="100000" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-emerald-300">
                    </div>
                    <input type="hidden" name="idempotency_key" value="{{ 'ven:tbl:' . bin2hex(random_bytes(6)) }}">
                    <div class="pt-2 flex justify-end space-x-2">
                        <button type="button" onclick="document.getElementById('modal-reserve').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-bold rounded-xl">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-slate-950 text-xs font-black rounded-xl">Konfirmasi Reservasi</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Settle Bill -->
        <div id="modal-settle" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 max-w-md w-full rounded-2xl p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-100">Penyelesaian Bill Meja (V4)</h3>
                    <button onclick="document.getElementById('modal-settle').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
                </div>
                <form id="form-settle" action="" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <span class="text-xs text-slate-400 block">Kode Reservasi:</span>
                        <span id="settle-bk-code" class="font-mono text-sm font-bold text-amber-400 block"></span>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Total Konsumsi Aktual Botol (IDR)</label>
                        <input type="number" name="actual_consumption_idr" id="settle-consumption-input" step="10000" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-mono text-emerald-300">
                        <span class="text-[11px] text-slate-500 block mt-1">Bila konsumsi < minimum spend, tamu tetap ditagih minimum spend penuh.</span>
                    </div>
                    <div class="pt-2 flex justify-end space-x-2">
                        <button type="button" onclick="document.getElementById('modal-settle').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs font-bold rounded-xl">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black rounded-xl">Proses Settlement</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function openSettleModal(bookingId, bookingCode, depositHeld) {
            document.getElementById('form-settle').action = '/venue/tables/' + bookingId + '/settle';
            document.getElementById('settle-bk-code').innerText = bookingCode;
            document.getElementById('settle-consumption-input').value = depositHeld;
            document.getElementById('modal-settle').classList.remove('hidden');
        }
    </script>
</body>
</html>
