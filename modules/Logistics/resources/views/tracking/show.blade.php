<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Status Pengiriman #{{ $trackingNumber }} | AutoServe Logistics</title>
    <meta name="description" content="Lacak status pengiriman resi {{ $trackingNumber }} secara real-time.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between selection:bg-blue-600 selection:text-white">

    {{-- Top Header --}}
    <header class="border-b border-slate-800/80 bg-slate-900/50 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="{{ route('track.index') }}" class="flex items-center gap-3 group">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center shadow-md shadow-blue-500/20 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                </div>
                <span class="font-bold text-base tracking-tight bg-gradient-to-r from-blue-400 to-indigo-300 bg-clip-text text-transparent">AutoServe Logistics</span>
            </a>

            {{-- Quick Search Bar in Header --}}
            <form action="{{ route('track.index') }}" method="GET" class="hidden sm:flex items-center gap-2">
                <div class="relative">
                    <input type="text"
                           name="q"
                           placeholder="Cek resi lain..."
                           class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500 font-mono uppercase w-48 transition-all">
                </div>
                <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-semibold transition">
                    Cek
                </button>
            </form>
        </div>
    </header>

    {{-- Main Tracking Card --}}
    <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-6">

        {{-- Tracking Overview Card --}}
        <div class="rounded-2xl bg-slate-900 border border-slate-800 p-6 sm:p-8 shadow-xl relative overflow-hidden">
            <div class="absolute top-0 right-0 w-96 h-96 bg-blue-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-800">
                <div class="space-y-1">
                    <div class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Nomor Resi / AWB</div>
                    <div class="flex items-center gap-3">
                        <span class="text-2xl sm:text-3xl font-mono font-black text-white tracking-wider">{{ $shipment->tracking_number }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    @php
                        $statusBadge = match($shipment->status->value) {
                            'draft' => 'bg-slate-700 text-slate-200 border-slate-600',
                            'booked' => 'bg-blue-900/60 text-blue-300 border-blue-700',
                            'picked_up' => 'bg-amber-900/60 text-amber-300 border-amber-700',
                            'in_transit' => 'bg-indigo-900/60 text-indigo-300 border-indigo-700',
                            'at_hub' => 'bg-purple-900/60 text-purple-300 border-purple-700',
                            'out_for_delivery' => 'bg-cyan-900/60 text-cyan-300 border-cyan-700',
                            'delivered' => 'bg-emerald-900/60 text-emerald-300 border-emerald-700',
                            'cancelled' => 'bg-rose-900/60 text-rose-300 border-rose-700',
                            default => 'bg-slate-800 text-slate-300 border-slate-700',
                        };
                    @endphp
                    <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold border uppercase tracking-wider {{ $statusBadge }}">
                        {{ $shipment->status->label() }}
                    </span>
                </div>
            </div>

            {{-- Route & Service Meta --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-6 text-sm">
                <div>
                    <span class="text-xs text-slate-400 block mb-1">Asal Pengiriman</span>
                    <span class="font-bold text-white block">{{ $shipment->origin?->name ?? 'Hub Asal' }}</span>
                    <span class="text-xs text-slate-400">{{ $shipment->origin?->city }}</span>
                </div>

                <div>
                    <span class="text-xs text-slate-400 block mb-1">Tujuan Pengiriman</span>
                    <span class="font-bold text-white block">{{ $shipment->destination?->name ?? 'Hub Tujuan' }}</span>
                    <span class="text-xs text-slate-400">{{ $shipment->destination?->city }}</span>
                </div>

                <div>
                    <span class="text-xs text-slate-400 block mb-1">Layanan & Moda</span>
                    <span class="font-bold text-white block capitalize">{{ $shipment->service_level->value }} ({{ $shipment->mode->value }})</span>
                    <span class="text-xs text-slate-400">{{ number_format($shipment->total_chargeable_weight_g / 1000, 2) }} kg (Chargeable)</span>
                </div>

                <div>
                    <span class="text-xs text-slate-400 block mb-1">Jumlah Paket</span>
                    <span class="font-bold text-white block">{{ $shipment->packages->count() }} Koli</span>
                    <span class="text-xs text-slate-400">{{ $shipment->packages->first()?->description ?? 'Barang Umum' }}</span>
                </div>
            </div>
        </div>

        {{-- Consignee Privacy Box & Timeline Layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left Column: Privacy-Masked Info --}}
            <div class="space-y-6">
                <div class="rounded-2xl bg-slate-900 border border-slate-800 p-6 space-y-4">
                    <div class="flex items-center gap-2 text-slate-300 font-semibold text-sm pb-3 border-b border-slate-800">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Informasi Penerima (PII Terproteksi)</span>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div>
                            <span class="text-slate-400 block mb-0.5">Nama Penerima</span>
                            <span class="font-mono font-medium text-slate-200 text-sm">{{ $maskedConsignee['name'] }}</span>
                        </div>

                        <div>
                            <span class="text-slate-400 block mb-0.5">Nomor Telepon</span>
                            <span class="font-mono font-medium text-slate-200 text-sm">{{ $maskedConsignee['phone'] }}</span>
                        </div>

                        <div>
                            <span class="text-slate-400 block mb-0.5">Alamat Tujuan</span>
                            <p class="font-mono text-slate-300 leading-relaxed">
                                {{ $maskedConsignee['address']['street'] }}<br>
                                {{ $maskedConsignee['address']['city'] }} {{ $maskedConsignee['address']['postal_code'] }}
                            </p>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-800/40 border border-slate-700/50 text-[11px] text-slate-400 leading-relaxed">
                        Data identitas penerima disamarkan secara otomatis sesuai standar privasi logistik untuk keamanan data Anda.
                    </div>
                </div>
            </div>

            {{-- Right Column: Tracking Timeline --}}
            <div class="lg:col-span-2">
                <div class="rounded-2xl bg-slate-900 border border-slate-800 p-6 sm:p-8 space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <div class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-ping"></div>
                            <h2 class="text-base font-bold text-white">Riwayat Perjalanan Kargo</h2>
                        </div>
                        <span class="text-xs text-slate-400">{{ count($events) }} Pembaruan</span>
                    </div>

                    <div class="relative pl-6 space-y-8 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-800">
                        @forelse($events as $index => $event)
                            <div class="relative group">
                                {{-- Timeline Marker --}}
                                @if($index === 0)
                                    <div class="absolute -left-6 top-1 w-5 h-5 rounded-full bg-blue-500 border-4 border-slate-900 shadow-lg shadow-blue-500/50 flex items-center justify-center">
                                    </div>
                                @else
                                    <div class="absolute -left-6 top-1.5 w-4 h-4 rounded-full bg-slate-700 border-2 border-slate-900 group-hover:bg-slate-600 transition">
                                    </div>
                                @endif

                                {{-- Event Details --}}
                                <div class="space-y-1">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                        <h3 class="font-bold text-sm {{ $index === 0 ? 'text-blue-400' : 'text-slate-200' }}">
                                            {{ $event['title'] }}
                                        </h3>
                                        <time class="text-xs font-mono text-slate-400">
                                            {{ $event['timestamp'] ? \Carbon\Carbon::parse($event['timestamp'])->timezone('Asia/Makassar')->format('d M Y, H:i') . ' WITA' : '-' }}
                                        </time>
                                    </div>
                                    <p class="text-xs text-slate-300 leading-relaxed">
                                        {{ $event['description'] }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-slate-400 italic">Belum ada riwayat pergerakan kargo.</div>
                        @endforelse
                    </div>
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
