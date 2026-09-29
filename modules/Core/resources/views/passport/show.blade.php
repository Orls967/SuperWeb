<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paspor Digital Kendaraan — {{ $vehicle->car ? $vehicle->car->brand->name . ' ' . $vehicle->car->model : $vehicle->plate_number }}</title>
    <!-- Google Fonts & Tailwind -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-100 font-sans antialiased min-h-screen selection:bg-amber-500 selection:text-black">
    <!-- Header -->
    <nav class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-40">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center font-black text-black shadow-lg shadow-amber-500/20">
                    VP
                </div>
                <div>
                    <span class="font-extrabold text-sm tracking-wider uppercase bg-gradient-to-r from-amber-400 to-orange-400 bg-clip-text text-transparent">Vehicle Passport</span>
                    <span class="text-3xs text-slate-400 block font-mono">Immutable Hash-Chain Digital Record</span>
                </div>
            </div>
            <div>
                <a href="{{ url('/') }}" class="text-xs text-slate-400 hover:text-white transition">
                    &larr; Beranda AutoServe
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        
        <!-- Verification Status Hero Banner -->
        @if($verification['is_valid'])
            <div class="bg-emerald-950/40 border border-emerald-500/40 rounded-3xl p-6 sm:p-8 backdrop-blur-xl relative overflow-hidden shadow-2xl shadow-emerald-950/30">
                <div class="absolute -right-8 -top-8 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl"></div>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 relative z-10">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-500/20 border border-emerald-500/50 flex-shrink-0 flex items-center justify-center text-emerald-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-mono mb-1">
                                Integritas Kriptografis 100% Terverifikasi
                            </div>
                            <h1 class="text-2xl sm:text-3xl font-black text-white">Paspor Digital Otentik</h1>
                            <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-xl">
                                Seluruh rantai catatan riwayat servis, penggantian sparepart, dan odometer telah diverifikasi matematis menggunakan hash-chain SHA-256. Tidak ada data yang diubah atau dimanipulasi.
                            </p>
                        </div>
                    </div>
                    <div class="text-left sm:text-right font-mono text-xs text-slate-400 border-t sm:border-t-0 sm:border-l border-slate-800 pt-3 sm:pt-0 sm:pl-6">
                        <div>TOTAL EVENT</div>
                        <div class="text-2xl font-black text-emerald-400">{{ $verification['event_count'] }}</div>
                        <div class="text-2xs text-slate-500">Immutable Blocks</div>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-rose-950/60 border border-rose-500/60 rounded-3xl p-6 sm:p-8 backdrop-blur-xl relative overflow-hidden shadow-2xl shadow-rose-950/40">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-rose-500/20 border border-rose-500/50 flex-shrink-0 flex items-center justify-center text-rose-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/30 font-mono mb-1">
                            Peringatan Keamanan Data
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black text-rose-200">Integritas Paspor Rusak!</h1>
                        <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-xl">
                            {{ $verification['message'] }} Rantai kriptografis terputus pada urutan ke-{{ $verification['broken_at_sequence'] }}.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Vehicle Details & QR Verification Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Vehicle Specs (2 Cols) -->
            <div class="md:col-span-2 bg-slate-900/80 border border-slate-800/80 rounded-3xl p-6 sm:p-8 backdrop-blur-xl space-y-6">
                <div>
                    <span class="text-xs font-mono font-semibold uppercase tracking-widest text-amber-400">Identitas Kendaraan</span>
                    <h2 class="text-2xl font-bold text-white mt-1">
                        {{ $vehicle->car ? $vehicle->car->brand->name . ' ' . $vehicle->car->model : 'Kendaraan Pribadi' }}
                    </h2>
                    @if($vehicle->car)
                        <p class="text-xs text-slate-400 mt-0.5">{{ $vehicle->car->year }} &bull; {{ $vehicle->car->specifications ?? 'Katalog AutoDex' }}</p>
                    @endif
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 font-mono text-xs">
                    <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800/60">
                        <div class="text-slate-400 text-2xs uppercase">Nomor Polisi</div>
                        <div class="text-sm font-bold text-white mt-1">{{ $vehicle->plate_number ?: '-' }}</div>
                    </div>
                    <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800/60">
                        <div class="text-slate-400 text-2xs uppercase">Odometer Terkini</div>
                        <div class="text-sm font-bold text-emerald-400 mt-1">{{ number_format($vehicle->odometer_km, 0, ',', '.') }} km</div>
                    </div>
                    <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800/60">
                        <div class="text-slate-400 text-2xs uppercase">Warna</div>
                        <div class="text-sm font-bold text-white mt-1">{{ $vehicle->color ?: '-' }}</div>
                    </div>
                    <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800/60">
                        <div class="text-slate-400 text-2xs uppercase">Nomor Rangka (VIN)</div>
                        <div class="text-sm font-bold text-slate-300 mt-1 truncate">{{ $vehicle->vin ?: 'ID-'.substr($vehicle->uuid, 0, 8) }}</div>
                    </div>
                    <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800/60">
                        <div class="text-slate-400 text-2xs uppercase">Pemilik Terdaftar</div>
                        <div class="text-sm font-bold text-amber-400 mt-1 font-sans">{{ $ownerName }}</div>
                    </div>
                    <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800/60">
                        <div class="text-slate-400 text-2xs uppercase">Status Kepemilikan</div>
                        <div class="text-sm font-bold text-white mt-1 uppercase">{{ $vehicle->status }}</div>
                    </div>
                </div>

                <div class="p-3 bg-slate-950/40 rounded-2xl border border-slate-800/40 flex items-center gap-2 text-2xs text-slate-400">
                    <svg class="w-4 h-4 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Privasi Dilindungi: Nama dan nomor telepon pemilik disamarkan sesuai protokol privasi kendaraan publik.</span>
                </div>
            </div>

            <!-- QR Code Card (1 Col) -->
            <div class="bg-slate-900/80 border border-slate-800/80 rounded-3xl p-6 sm:p-8 backdrop-blur-xl flex flex-col items-center justify-center text-center space-y-4">
                <span class="text-xs font-mono font-semibold uppercase tracking-widest text-slate-400">QR Verifikasi Instan</span>
                
                <div class="p-3 bg-white rounded-2xl shadow-xl flex items-center justify-center">
                    {!! $qrSvg !!}
                </div>

                <p class="text-2xs text-slate-400 max-w-xs font-mono">
                    Pindai dengan kamera ponsel untuk memverifikasi keabsahan paspor ini secara independen.
                </p>
            </div>

        </div>

        <!-- Hash-Chained Timeline of Events -->
        <div class="bg-slate-900/80 border border-slate-800/80 rounded-3xl p-6 sm:p-8 backdrop-blur-xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-white">Kronologi & Riwayat Servis Kriptografis</h3>
                    <p class="text-xs text-slate-400">Setiap rekaman dikunci menggunakan blok hash yang terhubung dengan event sebelumnya.</p>
                </div>
                <span class="text-xs font-mono px-3 py-1 bg-slate-800 rounded-full text-slate-300">
                    {{ count($events) }} Event
                </span>
            </div>

            @if($events->isEmpty())
                <div class="text-center py-12 text-slate-500 font-mono text-xs">
                    Belum ada riwayat tercatat pada kendaraan ini.
                </div>
            @else
                <div class="relative pl-6 space-y-8 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-800">
                    @foreach ($events as $event)
                        <div class="relative group">
                            <!-- Bullet Marker -->
                            <div class="absolute -left-[30px] top-1.5 w-3.5 h-3.5 rounded-full border-2 border-slate-900
                                @if($event->type->value === 'acquired') bg-amber-400 shadow-md shadow-amber-400/50
                                @elseif($event->type->value === 'service_completed') bg-blue-400 shadow-md shadow-blue-400/50
                                @elseif($event->type->value === 'part_replaced') bg-emerald-400 shadow-md shadow-emerald-400/50
                                @elseif($event->type->value === 'odometer_updated') bg-indigo-400 shadow-md shadow-indigo-400/50
                                @elseif($event->type->value === 'ownership_transferred') bg-purple-400 shadow-md shadow-purple-400/50
                                @else bg-slate-400 @endif">
                            </div>

                            <div class="bg-slate-950/70 border border-slate-800/70 hover:border-slate-700/70 rounded-2xl p-5 space-y-3 transition duration-200">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-800/50 pb-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-mono font-bold text-slate-400">#{{ $event->sequence }}</span>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono
                                            @if($event->type->value === 'acquired') bg-amber-500/20 text-amber-300 border border-amber-500/30
                                            @elseif($event->type->value === 'service_completed') bg-blue-500/20 text-blue-300 border border-blue-500/30
                                            @elseif($event->type->value === 'part_replaced') bg-emerald-500/20 text-emerald-300 border border-emerald-500/30
                                            @elseif($event->type->value === 'odometer_updated') bg-indigo-500/20 text-indigo-300 border border-indigo-500/30
                                            @elseif($event->type->value === 'ownership_transferred') bg-purple-500/20 text-purple-300 border border-purple-500/30
                                            @else bg-slate-800 text-slate-300 @endif">
                                            {{ $event->type->label() }}
                                        </span>
                                    </div>
                                    <span class="text-xs font-mono text-slate-400">
                                        {{ $event->occurred_at->format('d M Y, H:i:s T') }}
                                    </span>
                                </div>

                                <!-- Payload Details -->
                                <div class="text-xs text-slate-300 space-y-1 font-mono">
                                    @if($event->type->value === 'service_completed')
                                        <div class="font-sans font-semibold text-white text-sm">{{ $event->payload['service_name'] ?? 'Servis Berkala' }}</div>
                                        <div class="text-slate-400">Booking Ref: <span class="text-white">{{ $event->payload['booking_code'] ?? '-' }}</span></div>
                                        @if(isset($event->payload['mechanic_notes']))
                                            <div class="text-slate-400 italic">Catatan Mekanik: "{{ $event->payload['mechanic_notes'] }}"</div>
                                        @endif
                                        <div class="text-emerald-400 font-bold">Biaya: Rp {{ number_format((float)($event->payload['total_cost'] ?? 0), 0, ',', '.') }}</div>

                                    @elseif($event->type->value === 'part_replaced')
                                        <div class="font-sans font-semibold text-white">{{ $event->payload['part_name'] ?? 'Sparepart' }}</div>
                                        <div class="text-slate-400">Jumlah: <span class="text-white">{{ $event->payload['quantity'] ?? 1 }} pcs</span> (Kode: {{ $event->payload['part_code'] ?? '-' }})</div>
                                        <div class="text-amber-400">Subtotal: Rp {{ number_format((float)($event->payload['subtotal'] ?? 0), 0, ',', '.') }}</div>

                                    @elseif($event->type->value === 'odometer_updated')
                                        <div class="text-slate-400">Odometer Tercatat: <span class="text-emerald-400 font-bold text-sm">{{ number_format((int)($event->payload['odometer_km'] ?? 0), 0, ',', '.') }} km</span></div>

                                    @elseif($event->type->value === 'acquired')
                                        <div class="font-sans text-white">Kendaraan resmi diakuisisi ke garasi pemilik (Metode: <span class="capitalize font-mono text-amber-400">{{ $event->payload['method'] ?? 'manual' }}</span>)</div>
                                        @if(isset($event->payload['plate_number']))
                                            <div class="text-slate-400">Plat: {{ $event->payload['plate_number'] }} &bull; Warna: {{ $event->payload['color'] ?? '-' }}</div>
                                        @endif

                                    @elseif($event->type->value === 'ownership_transferred')
                                        <div class="font-sans text-purple-300 font-semibold">Pengalihan kepemilikan sukses via transaksi aman (Escrow C2C)</div>
                                        <div class="text-slate-400">Harga Jual: Rp {{ number_format((float)($event->payload['sale_price'] ?? 0), 0, ',', '.') }}</div>
                                    @endif
                                </div>

                                <!-- Hashes Block -->
                                <div class="pt-2 border-t border-slate-800/40 grid grid-cols-1 sm:grid-cols-2 gap-2 text-3xs font-mono text-slate-500">
                                    <div class="truncate" title="{{ $event->prev_hash }}">
                                        PREV: <span class="text-slate-400">{{ substr($event->prev_hash, 0, 16) }}...{{ substr($event->prev_hash, -8) }}</span>
                                    </div>
                                    <div class="truncate text-left sm:text-right" title="{{ $event->hash }}">
                                        HASH: <span class="text-emerald-400 font-semibold">{{ substr($event->hash, 0, 16) }}...{{ substr($event->hash, -8) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 py-8 text-center text-xs text-slate-500 font-mono">
        AutoServe &copy; {{ date('Y') }} &mdash; Cryptographically Secured Immutable Vehicle Passport
    </footer>
</body>
</html>
