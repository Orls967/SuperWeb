<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $booking->booking_code }} - AutoServe</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .print-container { box-shadow: none !important; border: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-8">

    {{-- Print Button --}}
    <div class="max-w-3xl mx-auto mb-4 flex items-center justify-between px-4 no-print">
        <a href="{{ route('bookings.show', $booking) }}" class="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 transition-colors">
            ← Kembali ke Detail
        </a>
        <button onclick="window.print()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition-all shadow-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Cetak Invoice
        </button>
    </div>

    {{-- Invoice --}}
    <div class="print-container max-w-3xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden">

        {{-- Header --}}
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white px-8 py-8">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold">AutoServe</h1>
                    </div>
                    <p class="text-sm text-slate-400">Bengkel Otomotif Terpadu</p>
                    <p class="text-xs text-slate-500 mt-1">Jl. Merdeka No. 123, Jakarta</p>
                </div>
                <div class="text-right">
                    <p class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-violet-400">INVOICE</p>
                    <p class="font-mono text-sm text-slate-300 mt-1">{{ $booking->booking_code }}</p>
                    <p class="text-xs text-slate-500 mt-1">{{ $booking->updated_at->format('d F Y') }}</p>
                </div>
            </div>
        </div>

        {{-- Customer & Vehicle Info --}}
        <div class="px-8 py-6 grid grid-cols-2 gap-8 bg-slate-50 border-b border-slate-200">
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Tagihan Kepada</p>
                <p class="font-bold text-slate-900">{{ $booking->customer->name }}</p>
                <p class="text-sm text-slate-600">{{ $booking->customer->email }}</p>
                <p class="text-sm text-slate-600">{{ $booking->customer->phone ?? '-' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Kendaraan</p>
                <p class="font-bold text-slate-900">{{ $booking->vehicle_brand }} {{ $booking->vehicle_model }}</p>
                <p class="text-sm text-slate-600 font-mono">{{ $booking->plate_number }}</p>
                <p class="text-sm text-slate-600">Tahun {{ $booking->vehicle_year ?? '-' }}</p>
            </div>
        </div>

        {{-- Items --}}
        <div class="px-8 py-6">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-slate-200">
                        <th class="text-left py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Item</th>
                        <th class="text-center py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Qty</th>
                        <th class="text-right py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Harga</th>
                        <th class="text-right py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Jasa Servis --}}
                    <tr class="border-b border-slate-100">
                        <td class="py-3">
                            <p class="font-medium text-slate-900">{{ $booking->service->name }}</p>
                            <p class="text-xs text-slate-500">Biaya jasa perbaikan</p>
                        </td>
                        <td class="py-3 text-center text-slate-600">1</td>
                        <td class="py-3 text-right text-slate-600">Rp {{ number_format($booking->service_cost, 0, ',', '.') }}</td>
                        <td class="py-3 text-right font-medium text-slate-900">Rp {{ number_format($booking->service_cost, 0, ',', '.') }}</td>
                    </tr>

                    {{-- Spareparts --}}
                    @foreach($booking->spareparts as $sp)
                    <tr class="border-b border-slate-100">
                        <td class="py-3">
                            <p class="font-medium text-slate-900">{{ $sp->name }}</p>
                            <p class="text-xs text-slate-500 font-mono">{{ $sp->code }}</p>
                        </td>
                        <td class="py-3 text-center text-slate-600">{{ $sp->pivot->quantity }} {{ $sp->unit }}</td>
                        <td class="py-3 text-right text-slate-600">Rp {{ number_format($sp->pivot->unit_price, 0, ',', '.') }}</td>
                        <td class="py-3 text-right font-medium text-slate-900">Rp {{ number_format($sp->pivot->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Totals --}}
            <div class="mt-6 flex justify-end">
                <div class="w-72 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Biaya Jasa</span>
                        <span class="text-slate-700">Rp {{ number_format($booking->service_cost, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Biaya Sparepart</span>
                        <span class="text-slate-700">Rp {{ number_format($booking->sparepart_cost, 0, ',', '.') }}</span>
                    </div>
                    <hr class="border-slate-200">
                    <div class="flex justify-between items-center pt-2">
                        <span class="font-bold text-slate-900 text-lg">GRAND TOTAL</span>
                        <span class="font-extrabold text-2xl text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-violet-600">
                            Rp {{ number_format($booking->grand_total, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Mekanik Notes --}}
        @if($booking->mechanic_notes)
        <div class="px-8 py-4 bg-slate-50 border-t border-slate-200">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Catatan Mekanik</p>
            <p class="text-sm text-slate-600">{{ $booking->mechanic_notes }}</p>
        </div>
        @endif

        {{-- Footer --}}
        <div class="px-8 py-6 bg-gradient-to-r from-slate-900 to-slate-800 text-center">
            <p class="text-sm text-slate-400">Terima kasih telah mempercayakan kendaraan Anda kepada <span class="text-white font-semibold">AutoServe</span></p>
            <p class="text-xs text-slate-500 mt-1">Invoice ini sah tanpa tanda tangan dan stempel</p>
            @if($booking->mechanic)
            <p class="text-xs text-slate-500 mt-2">Ditangani oleh: <span class="text-slate-300">{{ $booking->mechanic->name }}</span></p>
            @endif
        </div>
    </div>
</body>
</html>
