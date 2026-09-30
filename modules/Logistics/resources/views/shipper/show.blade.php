<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('logistics.shipments.index') }}" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-2xl text-white tracking-tight font-mono">{{ $shipment->tracking_number }}</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $shipment->status->badgeClass() }}">
                            {{ $shipment->status->label() }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Dipesan pada {{ $shipment->created_at->format('d M Y, H:i') }} • Layanan {{ $shipment->service_level->label() }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('logistics.shipments.label', $shipment->id) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-semibold transition border border-slate-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Cetak Label Resi QR
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-900/40 border border-emerald-500/40 text-emerald-300 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left 2 Cols: Route & Package Breakdown -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Route Card -->
                    <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl backdrop-blur-md">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">Informasi Rute & Pengiriman</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="p-4 rounded-xl bg-slate-950/50 border border-slate-800/80">
                                <div class="text-xs text-sky-400 font-semibold mb-1">ASAL (ORIGIN)</div>
                                <div class="text-base font-bold text-white">{{ $shipment->origin?->name }}</div>
                                <div class="text-xs text-slate-400 mt-1">{{ $shipment->origin?->city }}, {{ $shipment->origin?->province }}</div>
                                <div class="text-xs text-slate-500 font-mono mt-1">{{ $shipment->origin?->code }}</div>
                            </div>

                            <div class="p-4 rounded-xl bg-slate-950/50 border border-slate-800/80">
                                <div class="text-xs text-indigo-400 font-semibold mb-1">TUJUAN (DESTINATION)</div>
                                <div class="text-base font-bold text-white">{{ $shipment->destination?->name }}</div>
                                <div class="text-xs text-slate-400 mt-1">{{ $shipment->destination?->city }}, {{ $shipment->destination?->province }}</div>
                                <div class="text-xs text-slate-500 font-mono mt-1">{{ $shipment->destination?->code }}</div>
                            </div>
                        </div>

                        <div class="mt-6 pt-6 border-t border-slate-800 grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <div class="text-xs text-slate-500">Moda Transportasi</div>
                                <div class="text-sm font-semibold text-white mt-0.5">{{ $shipment->mode->label() }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Ketentuan Pembayaran</div>
                                <div class="text-sm font-semibold text-white mt-0.5">{{ $shipment->payment_terms->label() }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Berat Tertagih</div>
                                <div class="text-sm font-semibold text-white mt-0.5">{{ number_format($shipment->total_chargeable_weight_g / 1000, 1) }} kg</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Driver Bertugas</div>
                                <div class="text-sm font-semibold text-white mt-0.5">{{ $shipment->driver?->user?->name ?? 'Belum Ditugaskan' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Packages List -->
                    <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl backdrop-blur-md">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">Rincian Paket Kargo ({{ $shipment->packages->count() }} Paket)</h3>
                        <div class="divide-y divide-slate-800">
                            @foreach($shipment->packages as $idx => $pkg)
                                <div class="py-3.5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                                    <div>
                                        <div class="font-medium text-white text-sm">
                                            #{{ $idx + 1 }} — {{ $pkg->description }}
                                        </div>
                                        <div class="text-xs text-slate-500 mt-0.5">
                                            Dimensi: {{ $pkg->length_mm }} × {{ $pkg->width_mm }} × {{ $pkg->height_mm }} mm
                                            @if($pkg->isDangerousGoods())
                                                • <span class="text-rose-400 font-semibold">DG {{ $pkg->dg_un_number }}</span>
                                            @endif
                                            @if($pkg->isReefer())
                                                • <span class="text-cyan-400 font-semibold">Reefer ({{ $pkg->temp_min_c10 / 10 }}°C s/d {{ $pkg->temp_max_c10 / 10 }}°C)</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-mono font-semibold text-slate-300 text-sm">{{ number_format($pkg->weight_g / 1000, 2) }} kg</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Right Col: Consignee & Price Summary -->
                <div class="space-y-6">
                    <!-- Consignee Card -->
                    <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl backdrop-blur-md">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Penerima Kargo</h3>
                        <div class="space-y-2">
                            <div class="text-base font-bold text-white">{{ $shipment->consignee_name }}</div>
                            <div class="text-sm font-mono text-sky-400">{{ $shipment->consignee_phone }}</div>
                            <p class="text-xs text-slate-300 leading-relaxed mt-2 p-3 rounded-xl bg-slate-950/60 border border-slate-800">
                                {{ $shipment->consignee_address['street'] ?? '-' }}<br>
                                {{ $shipment->consignee_address['city'] ?? '' }} {{ $shipment->consignee_address['postal_code'] ?? '' }}
                            </p>
                        </div>
                    </div>

                    <!-- Price Card -->
                    <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl backdrop-blur-md">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">Ringkasan Biaya</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between text-slate-400">
                                <span>Nilai Dideklarasikan</span>
                                <span class="font-mono text-slate-200">Rp {{ number_format($shipment->declared_value_idr, 0, ',', '.') }}</span>
                            </div>
                            @if($shipment->cod_amount_idr > 0)
                                <div class="flex justify-between text-slate-400">
                                    <span>Tagihan COD</span>
                                    <span class="font-mono text-amber-400">Rp {{ number_format($shipment->cod_amount_idr, 0, ',', '.') }}</span>
                                </div>
                            @endif
                            <div class="pt-3 border-t border-slate-800 flex justify-between items-center">
                                <span class="font-bold text-white">Total Ongkir</span>
                                <span class="font-mono font-bold text-lg text-emerald-400">
                                    Rp {{ number_format($shipment->total_amount_idr, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
