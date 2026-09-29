<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $booking->booking_code }} - AutoServe</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .print-container { box-shadow: none !important; border: none !important; }
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen py-8"
      x-data="{
          payModal: false,
          refundModal: false,
          userBalance: {{ (float) auth()->user()->walletBalance('IDR')->amount->__toString() }},
          grandTotal: {{ (float) $booking->grand_total }},
          pin: '',
          refundReason: '',
          refundAmount: '{{ (float) $booking->grand_total }}',
          get canAfford() {
              return this.userBalance >= this.grandTotal;
          },
          get balanceAfter() {
              return Math.max(0, this.userBalance - this.grandTotal);
          },
          formatRupiah(num) {
              return 'Rp ' + (new Intl.NumberFormat('id-ID').format(num || 0));
          }
      }">

    {{-- Top Action Bar --}}
    <div class="max-w-3xl mx-auto mb-6 flex flex-wrap items-center justify-between gap-4 px-4 no-print">
        <a href="{{ route('bookings.show', $booking) }}" class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors">
            &larr; Kembali ke Detail Booking
        </a>

        <div class="flex items-center gap-3">
            @if($booking->isPaid())
                <span class="px-3 py-1.5 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    LUNAS
                </span>

                @if(auth()->user()->isAdmin())
                    <button
                        @click="refundModal = true"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-rose-600/30 hover:bg-rose-600/50 text-rose-300 border border-rose-500/40 text-xs font-semibold transition-all">
                        Refund Dana
                    </button>
                @endif
            @elseif($booking->isRefunded())
                <span class="px-3 py-1.5 rounded-xl bg-purple-500/20 text-purple-400 border border-purple-500/30 text-xs font-bold">
                    REFUNDED
                </span>
            @else
                <button
                    @click="payModal = true"
                    type="button"
                    class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white text-xs font-bold transition-all shadow-lg shadow-emerald-500/25 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Bayar dengan Saldo Dompet
                </button>
            @endif

            <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800 text-slate-200 text-xs font-medium hover:bg-slate-700 transition-all border border-slate-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak
            </button>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="max-w-3xl mx-auto mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm no-print">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="max-w-3xl mx-auto mb-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm no-print">
            {{ session('error') }}
        </div>
    @endif

    {{-- Printable Invoice Document --}}
    <div class="print-container max-w-3xl mx-auto bg-slate-800 rounded-2xl shadow-2xl overflow-hidden border border-slate-700/60">

        {{-- Header --}}
        <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 text-white px-8 py-8 border-b border-slate-700/60">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/20">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                        <h1 class="text-2xl font-extrabold tracking-tight">AutoServe</h1>
                    </div>
                    <p class="text-xs text-slate-400">Bengkel Otomotif Terpadu & Layanan Keuangan Terintegrasi</p>
                    <p class="text-xs text-slate-500 mt-1">Jl. Otomotif Super Monolith No. 1, Jakarta</p>
                </div>
                <div class="text-right">
                    <p class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-300">INVOICE</p>
                    <p class="font-mono text-sm text-slate-300 mt-1 font-bold">{{ $booking->booking_code }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ $booking->updated_at->format('d F Y, H:i') }} WIB</p>

                    @if($booking->isPaid())
                        <div class="mt-3 inline-block px-3 py-1 rounded-lg bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs font-extrabold tracking-widest uppercase">
                            ✓ LUNAS
                        </div>
                    @elseif($booking->isRefunded())
                        <div class="mt-3 inline-block px-3 py-1 rounded-lg bg-purple-500/20 border border-purple-500/40 text-purple-300 text-xs font-extrabold tracking-widest uppercase">
                            REFUNDED
                        </div>
                    @else
                        <div class="mt-3 inline-block px-3 py-1 rounded-lg bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs font-extrabold tracking-widest uppercase">
                            MENUNGGU PEMBAYARAN
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Customer & Vehicle Info --}}
        <div class="px-8 py-6 grid grid-cols-1 sm:grid-cols-2 gap-6 bg-slate-900/40 border-b border-slate-700/60 text-xs">
            <div>
                <p class="font-bold text-slate-400 uppercase tracking-widest mb-1.5 text-[10px]">Tagihan Kepada</p>
                <p class="font-bold text-white text-sm">{{ $booking->customer->name }}</p>
                <p class="text-slate-400 font-mono mt-0.5">{{ $booking->customer->email }}</p>
                <p class="text-slate-400 mt-0.5">{{ $booking->customer->phone ?? '-' }}</p>
            </div>
            <div>
                <p class="font-bold text-slate-400 uppercase tracking-widest mb-1.5 text-[10px]">Data Kendaraan</p>
                <p class="font-bold text-white text-sm">{{ $booking->vehicle_brand }} {{ $booking->vehicle_model }}</p>
                <p class="text-slate-400 font-mono mt-0.5">Plat: {{ $booking->plate_number }}</p>
                <p class="text-slate-400 mt-0.5">Tahun: {{ $booking->vehicle_year ?? '-' }}</p>
            </div>
        </div>

        {{-- Line Items Table --}}
        <div class="px-8 py-6">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-700/80 text-slate-400 uppercase font-semibold tracking-wider">
                        <th class="py-3">Rincian Perbaikan & Komponen</th>
                        <th class="py-3 text-center">Qty</th>
                        <th class="py-3 text-right">Tarif Satuan</th>
                        <th class="py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40 text-slate-300">
                    {{-- Jasa Servis --}}
                    <tr>
                        <td class="py-3.5">
                            <p class="font-semibold text-white text-sm">{{ $booking->service->name }}</p>
                            <p class="text-[11px] text-slate-400">Jasa Teknisi & Pemeriksaan Servis</p>
                        </td>
                        <td class="py-3.5 text-center font-mono">1</td>
                        <td class="py-3.5 text-right font-mono">Rp {{ number_format((float) $booking->service_cost, 0, ',', '.') }}</td>
                        <td class="py-3.5 text-right font-mono font-semibold text-white">Rp {{ number_format((float) $booking->service_cost, 0, ',', '.') }}</td>
                    </tr>

                    {{-- Spareparts --}}
                    @foreach($booking->spareparts as $sp)
                    <tr>
                        <td class="py-3.5">
                            <p class="font-semibold text-white text-sm">{{ $sp->name }}</p>
                            <p class="text-[11px] text-slate-400 font-mono">Kode: {{ $sp->code }}</p>
                        </td>
                        <td class="py-3.5 text-center font-mono">{{ $sp->pivot->quantity }} {{ $sp->unit }}</td>
                        <td class="py-3.5 text-right font-mono">Rp {{ number_format((float) $sp->pivot->unit_price, 0, ',', '.') }}</td>
                        <td class="py-3.5 text-right font-mono font-semibold text-white">Rp {{ number_format((float) $sp->pivot->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Cost Summary Breakdown --}}
            <div class="mt-6 pt-4 border-t border-slate-700/60 flex justify-end">
                <div class="w-80 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>Total Biaya Jasa Servis</span>
                        <span class="font-mono text-slate-200">Rp {{ number_format((float) $booking->service_cost, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Total Biaya Sparepart</span>
                        <span class="font-mono text-slate-200">Rp {{ number_format((float) $booking->sparepart_cost, 0, ',', '.') }}</span>
                    </div>
                    <div class="pt-2 border-t border-slate-700/60 flex justify-between items-center text-sm font-bold text-white">
                        <span>TOTAL TAGIHAN</span>
                        <span class="text-xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-300 font-mono">
                            Rp {{ number_format((float) $booking->grand_total, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment Settlement Information --}}
        @if($booking->isPaid())
            @php
                $intent = $booking->latestPaymentIntent;
                $tx = $intent?->captureTransaction;
            @endphp
            <div class="px-8 py-5 bg-emerald-950/30 border-t border-emerald-500/20 text-xs">
                <div class="flex items-center gap-2 text-emerald-400 font-bold mb-2">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    BUKTI PEMBAYARAN TEREKONSILIASI
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-slate-300 font-mono text-[11px]">
                    <div>
                        <span class="text-slate-400 font-sans">Waktu Pelunasan:</span>
                        {{ $booking->paid_at?->format('d/m/Y H:i:s') }} WIB
                    </div>
                    <div>
                        <span class="text-slate-400 font-sans">Payment Intent:</span>
                        {{ $intent?->uuid ?? '-' }}
                    </div>
                    @if($tx)
                    <div>
                        <span class="text-slate-400 font-sans">No. Transaksi Ledger:</span>
                        {{ $tx->uuid }}
                    </div>
                    <div>
                        <span class="text-slate-400 font-sans">Idempotency Key:</span>
                        {{ $tx->idempotency_key }}
                    </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Footer --}}
        <div class="px-8 py-6 bg-slate-900/80 border-t border-slate-700/60 text-center text-xs text-slate-400">
            <p>Terima kasih telah mempercayakan perbaikan kendaraan Anda kepada AutoServe.</p>
            <p class="text-[11px] text-slate-500 mt-1">Invoice ini sah sebagai bukti pembayaran elektronik double-entry ledger platform.</p>
        </div>
    </div>

    {{-- Alpine Pay Modal --}}
    <div x-show="payModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto no-print" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="payModal" x-transition.opacity class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="payModal = false"></div>

            <div x-show="payModal" x-transition class="inline-block align-bottom bg-slate-800 rounded-2xl border border-slate-700 text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-700">
                    <h3 class="text-base font-bold text-white">Bayar Invoice dengan Saldo</h3>
                    <button @click="payModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="mt-4 space-y-4">
                    {{-- Balance Summary Box --}}
                    <div class="p-4 rounded-xl bg-slate-900/70 border border-slate-700 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-300">
                            <span>Saldo Dompet Anda</span>
                            <span class="font-mono font-bold text-white" x-text="formatRupiah(userBalance)"></span>
                        </div>
                        <div class="flex justify-between text-slate-300">
                            <span>Total Tagihan Invoice</span>
                            <span class="font-mono font-bold text-rose-400" x-text="formatRupiah(grandTotal)"></span>
                        </div>
                        <div class="pt-2 border-t border-slate-700 flex justify-between font-bold text-sm">
                            <span class="text-slate-300">Sisa Saldo Setelah Bayar</span>
                            <span class="font-mono text-emerald-400" x-text="formatRupiah(balanceAfter)"></span>
                        </div>
                    </div>

                    @if(!auth()->user()->hasPin())
                        <div class="p-3 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs">
                            ⚠️ Anda belum mengatur PIN transaksi. Silakan atur PIN terlebih dahulu di menu Dompet.
                        </div>
                    @endif

                    <div x-show="!canAfford" class="p-3 rounded-xl bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs">
                        <p class="font-bold">Saldo Tidak Mencukupi!</p>
                        <p class="mt-1">Silakan lakukan Top Up saldo dompet Anda terlebih dahulu.</p>
                        <a href="{{ route('wallet.index') }}" class="inline-block mt-2 font-bold underline text-white">
                            Top Up Saldo Sekarang &rarr;
                        </a>
                    </div>

                    <form method="POST" action="{{ route('bookings.pay', $booking) }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="pay_pin" class="block text-xs font-medium text-slate-300 mb-1 text-center">
                                Masukkan PIN Transaksi 6-Digit
                            </label>
                            <input
                                type="password"
                                id="pay_pin"
                                name="pin"
                                x-model="pin"
                                maxlength="6"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                required
                                :disabled="!canAfford"
                                placeholder="••••••"
                                class="w-full px-4 py-3 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono text-center text-2xl tracking-[0.4em] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div class="pt-4 border-t border-slate-700 flex justify-end gap-3">
                            <button type="button" @click="payModal = false" class="px-4 py-2 rounded-xl bg-slate-700 text-slate-300 text-xs font-medium hover:bg-slate-600">
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="!canAfford || pin.length !== 6"
                                class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all disabled:opacity-40 shadow-lg shadow-emerald-600/30">
                                Konfirmasi & Bayar Tagihan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Admin Refund Modal --}}
    @if(auth()->user()->isAdmin())
    <div x-show="refundModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto no-print" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="refundModal" x-transition.opacity class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="refundModal = false"></div>

            <div x-show="refundModal" x-transition class="inline-block align-bottom bg-slate-800 rounded-2xl border border-slate-700 text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-700">
                    <h3 class="text-base font-bold text-white">Refund Pembayaran Invoice</h3>
                    <button @click="refundModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form method="POST" action="{{ route('bookings.refund', $booking) }}" class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <label for="refund_amount" class="block text-xs font-medium text-slate-300 mb-1">Nominal Refund (Rp)</label>
                        <input
                            type="number"
                            id="refund_amount"
                            name="amount"
                            x-model="refundAmount"
                            min="1"
                            step="any"
                            required
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono text-sm">
                    </div>

                    <div>
                        <label for="refund_reason" class="block text-xs font-medium text-slate-300 mb-1">Alasan Refund (Wajib)</label>
                        <textarea
                            id="refund_reason"
                            name="reason"
                            rows="3"
                            required
                            placeholder="Contoh: Kesalahan input sparepart / kompensasi keluhan pelanggan"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs"></textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-700 flex justify-end gap-3">
                        <button type="button" @click="refundModal = false" class="px-4 py-2 rounded-xl bg-slate-700 text-slate-300 text-xs font-medium hover:bg-slate-600">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition-all shadow-md shadow-rose-600/30">
                            Proses Refund Dana
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

</body>
</html>
