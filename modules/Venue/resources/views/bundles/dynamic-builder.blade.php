@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-8 px-4 sm:px-6 lg:px-8" x-data="bundleBuilderApp()">
    {{-- Toast Notification --}}
    <div x-show="toast.visible" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-5 right-5 z-50 flex items-center p-4 rounded-xl shadow-2xl border"
         :class="toast.type === 'success' ? 'bg-emerald-950 border-emerald-500 text-emerald-200' : 'bg-rose-950 border-rose-500 text-rose-200'"
         style="display: none;">
        <span class="mr-3 text-lg" x-text="toast.type === 'success' ? '✅' : '⚠️'"></span>
        <div>
            <p class="font-semibold text-sm" x-text="toast.title"></p>
            <p class="text-xs opacity-90" x-text="toast.message"></p>
        </div>
    </div>

    {{-- Main Container --}}
    <div class="max-w-7xl mx-auto space-y-8">
        {{-- Header Section --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        Dynamic Bundling Engine V10
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Floor Cost Guardrail Aktif
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        Integrasi Hotel G7-K20 & Shuttle G7-K21
                    </span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white mt-2">
                    Perancang Paket Bundling Dinamis Multi-Sektor
                </h1>
                <p class="text-sm text-slate-400 mt-1 max-w-3xl">
                    Integrasi otomatis alokasi hotel mitra (G7-K20), armada shuttle logistik (G7-K21), tiket festival, dan guardrail margin dasar matematis. Menjamin tidak ada paket yang dijual merugi.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('venue.secondary.marketplace') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold rounded-xl border border-slate-700 text-sm">
                    ← Lihat di Marketplace
                </a>
            </div>
        </div>

        {{-- Success alert --}}
        @if(session('success'))
            <div class="p-4 bg-emerald-500/20 border border-emerald-500/40 rounded-xl text-emerald-300 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- Interactive Builder Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            {{-- Left Column: Configuration Form (7 cols) --}}
            <div class="lg:col-span-7 bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 shadow-xl space-y-6">
                <div class="border-b border-slate-700/60 pb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-white">Parameter Paket & Komponen</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Konfigurasikan tiket, akomodasi, armada penjemputan, dan voucher F&B.</p>
                    </div>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-cyan-500/20 text-cyan-300">BUILDER MODE</span>
                </div>

                <form method="POST" action="{{ route('venue.secondary.bundles.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-400 mb-1.5">Acara Festival Terpilih</label>
                        <select name="event_id" x-model.number="selectedEventId" required
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-500">
                            @foreach($events as $e)
                                <option value="{{ $e->id }}">{{ $e->event_code }} — {{ $e->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-400 mb-1.5">Nama Paket Bundling</label>
                        <input type="text" name="package_name" x-model="packageName" required
                               class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-500"
                               placeholder="e.g. Sunset Luxury Weekend Getaway (VIP + Suite + Shuttle)">
                    </div>

                    {{-- Dynamic Occupancy & Lead Time Sliders --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-slate-900/60 rounded-xl border border-slate-700/60">
                        <div>
                            <div class="flex justify-between text-xs text-slate-400 mb-1">
                                <span>Okupansi Venue:</span>
                                <span class="font-bold text-cyan-400" x-text="venueOccupancy + '%'"></span>
                            </div>
                            <input type="range" name="occupancy_percentage" x-model.number="venueOccupancy" @input="recalcDynamicPrice()"
                                   min="10" max="100" class="w-full accent-cyan-500 cursor-pointer">
                            <div class="text-[10px] text-slate-500 mt-1">Surge > 70%</div>
                        </div>

                        <div>
                            <div class="flex justify-between text-xs text-slate-400 mb-1">
                                <span>Okupansi Hotel:</span>
                                <span class="font-bold text-indigo-400" x-text="hotelOccupancy + '%'"></span>
                            </div>
                            <input type="range" x-model.number="hotelOccupancy" @input="recalcDynamicPrice()"
                                   min="10" max="100" class="w-full accent-indigo-500 cursor-pointer">
                            <div class="text-[10px] text-slate-500 mt-1">Alokasi kamar mitra</div>
                        </div>

                        <div>
                            <div class="flex justify-between text-xs text-slate-400 mb-1">
                                <span>Lead Time Pemesanan:</span>
                                <span class="font-bold text-amber-400" x-text="leadTimeDays + ' Hari'"></span>
                            </div>
                            <input type="range" name="lead_time_days" x-model.number="leadTimeDays" @input="recalcDynamicPrice()"
                                   min="1" max="60" class="w-full accent-amber-500 cursor-pointer">
                            <div class="text-[10px] text-slate-500 mt-1">Diskon early-bird</div>
                        </div>
                    </div>

                    {{-- Component Items List --}}
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-xs font-bold uppercase text-slate-400">Komponen Bundling (Tiket, Hotel, Shuttle, F&B)</label>
                            <button type="button" @click="addComponent()" class="text-xs text-cyan-400 hover:text-cyan-300 font-semibold flex items-center gap-1">
                                + Tambah Komponen
                            </button>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(comp, index) in components" :key="index">
                                <div class="p-4 bg-slate-900 border border-slate-700 rounded-xl space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-cyan-400" x-text="'Komponen #' + (index + 1)"></span>
                                        <button type="button" @click="removeComponent(index)" x-show="components.length > 1" class="text-rose-400 hover:text-rose-300 text-xs">
                                            Hapus
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div>
                                            <input type="text" :name="'components[' + index + '][name]'" x-model="comp.name" required
                                                   class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white" placeholder="Nama item">
                                        </div>
                                        <div>
                                            <select :name="'components[' + index + '][component_type]'" x-model="comp.component_type"
                                                    class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                                                <option value="TICKET">Tiket Event / VIP</option>
                                                <option value="HOTEL">Kamar Hotel Mitra (G7-K20)</option>
                                                <option value="SHUTTLE">Shuttle Armada Transport (G7-K21)</option>
                                                <option value="DINING">F&B Credit Dining</option>
                                            </select>
                                        </div>
                                        <div class="flex gap-2">
                                            <div class="w-1/2">
                                                <input type="number" :name="'components[' + index + '][floor_cost]'" x-model.number="comp.floor_cost" @input="recalcDynamicPrice()"
                                                       placeholder="Floor" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white" title="Biaya Dasar (Floor Cost)">
                                            </div>
                                            <div class="w-1/2">
                                                <input type="number" :name="'components[' + index + '][base_price]'" x-model.number="comp.base_price" @input="recalcDynamicPrice()"
                                                       placeholder="Base" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white" title="Harga Jual Asli">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-700/60 flex items-center justify-end">
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold text-sm rounded-xl shadow-lg">
                            Publikasikan Paket Dynamic Bundle
                        </button>
                    </div>
                </form>
            </div>

            {{-- Right Column: Live Pricing & Guardrail Simulator (5 cols) --}}
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-gradient-to-br from-slate-800 via-slate-800/90 to-slate-900 border border-cyan-500/30 rounded-2xl p-6 shadow-2xl relative overflow-hidden">
                    <div class="absolute -right-8 -top-8 w-32 h-32 bg-cyan-500/10 rounded-full blur-2xl"></div>

                    <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
                        <h4 class="text-xs font-black uppercase tracking-wider text-cyan-400">Hasil Simulasi Algoritma V10</h4>
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-cyan-500/20 text-cyan-300">LIVE GUARDS</span>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div>
                            <div class="text-xs text-slate-400">Total Harga Dasar (Sum Base Price):</div>
                            <div class="text-xl font-bold text-white mt-0.5" x-text="'Rp ' + totalBasePrice.toLocaleString('id-ID')"></div>
                        </div>

                        <div>
                            <div class="text-xs text-slate-400">Total Biaya Pokok (Sum Floor Cost):</div>
                            <div class="text-xl font-bold text-rose-300 mt-0.5" x-text="'Rp ' + totalFloorCost.toLocaleString('id-ID')"></div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <div class="p-3 bg-slate-900/80 rounded-xl border border-slate-700/60">
                                <div class="text-[10px] uppercase text-slate-400">Lonjakan Okupansi</div>
                                <div class="text-sm font-bold text-amber-400 mt-1" x-text="'+' + (surgeBps / 100).toFixed(1) + '%'"></div>
                                <div class="text-[9px] text-slate-500" x-text="surgeBps + ' BPS'"></div>
                            </div>

                            <div class="p-3 bg-slate-900/80 rounded-xl border border-slate-700/60">
                                <div class="text-[10px] uppercase text-slate-400">Diskon Early-Bird</div>
                                <div class="text-sm font-bold text-emerald-400 mt-1" x-text="'-' + (discountBps / 100).toFixed(1) + '%'"></div>
                                <div class="text-[9px] text-slate-500" x-text="discountBps + ' BPS'"></div>
                            </div>
                        </div>

                        {{-- Final Price with Floor Margin Guardrail indicator --}}
                        <div class="p-4 bg-slate-900/90 rounded-xl border border-emerald-500/40 space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Harga Akhir Paket:</span>
                                <span class="text-xs px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold">GUARDRAIL OK</span>
                            </div>
                            <div class="text-3xl font-black text-white" x-text="'Rp ' + finalPrice.toLocaleString('id-ID')"></div>
                            <div class="text-[11px] text-slate-400">
                                Margin bersih di atas floor cost: <span class="text-emerald-400 font-bold" x-text="'Rp ' + (finalPrice - totalFloorCost).toLocaleString('id-ID')"></span>
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-400 leading-relaxed">
                            💡 <strong>Guardrail Floor Margin:</strong> Algoritma secara matematis menolak penetapan harga di bawah total biaya dasar (<code class="text-rose-300">FinalPrice &gt;= TotalFloorCost</code>) guna menjamin tidak ada kerugian operasional pada kamar hotel maupun shuttle logistik.
                        </div>
                    </div>
                </div>

                {{-- Registered Packages Preview --}}
                <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 shadow-lg space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Paket Terdaftar Aktif</h4>
                    <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                        @forelse($bundles as $pkg)
                            <div class="p-3 bg-slate-900/70 rounded-xl border border-slate-700/50 flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-bold text-white">{{ $pkg->package_name }}</div>
                                    <div class="text-slate-400 text-[11px] mt-0.5">{{ $pkg->package_code }} • {{ $pkg->components->count() }} Item</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-black text-amber-400">Rp {{ number_format($pkg->final_price_idr, 0, ',', '.') }}</div>
                                    <span class="text-[10px] text-emerald-400 font-semibold">{{ $pkg->status }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-500 text-center py-4">Belum ada paket bundling yang tersimpan.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function bundleBuilderApp() {
    return {
        selectedEventId: {{ $events->first()?->id ?? 1 }},
        packageName: 'Sunset Luxury Weekend Getaway (VIP + Suite + Shuttle)',
        venueOccupancy: 85,
        hotelOccupancy: 80,
        leadTimeDays: 7,
        components: [
            { name: 'Tiket VIP Sunset Sound', component_type: 'TICKET', floor_cost: 400000, base_price: 800000 },
            { name: 'Kamar Deluxe Hotel Mitra', component_type: 'HOTEL', floor_cost: 600000, base_price: 1200000 },
            { name: 'Airport Shuttle Bus VIP', component_type: 'SHUTTLE', floor_cost: 100000, base_price: 200000 }
        ],
        totalBasePrice: 0,
        totalFloorCost: 0,
        surgeBps: 0,
        discountBps: 0,
        finalPrice: 0,
        toast: { visible: false, title: '', message: '', type: 'success' },

        init() {
            this.recalcDynamicPrice();
        },

        addComponent() {
            this.components.push({
                name: 'Voucher F&B Dining',
                component_type: 'DINING',
                floor_cost: 150000,
                base_price: 250000
            });
            this.recalcDynamicPrice();
        },

        removeComponent(index) {
            this.components.splice(index, 1);
            this.recalcDynamicPrice();
        },

        recalcDynamicPrice() {
            this.totalBasePrice = this.components.reduce((acc, c) => acc + Number(c.base_price || 0), 0);
            this.totalFloorCost = this.components.reduce((acc, c) => acc + Number(c.floor_cost || 0), 0);

            // Okupansi surge
            const avgOcc = Math.floor((this.venueOccupancy + this.hotelOccupancy) / 2);
            this.surgeBps = avgOcc > 70 ? (avgOcc - 70) * 70 : 0;

            // Lead time discount
            if (this.leadTimeDays >= 30) {
                this.discountBps = 1500;
            } else if (this.leadTimeDays >= 14) {
                this.discountBps = 1000;
            } else if (this.leadTimeDays >= 7) {
                this.discountBps = 500;
            } else {
                this.discountBps = 0;
            }

            const surgeAmt = Math.floor((this.totalBasePrice * this.surgeBps) / 10000);
            const discAmt = Math.floor((this.totalBasePrice * this.discountBps) / 10000);
            const rawCalculated = this.totalBasePrice + surgeAmt - discAmt;

            // Guardrail floor cost enforcement
            this.finalPrice = Math.max(rawCalculated, this.totalFloorCost);
        }
    };
}
</script>
@endsection

{{-- Multi-Vendor Margin & Revenue Split Waterfall Preview --}}
<div class="mt-8 bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 shadow-xl space-y-6">
    <div class="border-b border-slate-700/60 pb-3 flex items-center justify-between">
        <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Proyeksi Pembagian Hasil Multi-Vendor (Inter-Module Revenue Split)
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Alokasi otomatis ke sub-ledger mitra: Hotel Mitra (G7-K20), Shuttle Logistik (G7-K21), dan Tiket Venue.</p>
        </div>
        <span class="text-xs font-mono px-2.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold">CROSS-MODULE REVENUE</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
        <div class="p-4 bg-slate-900 rounded-xl border border-slate-800 space-y-2">
            <div class="text-slate-400 font-bold uppercase text-[10px]">Tiket Venue (Beach Club / Festival)</div>
            <div class="text-lg font-black text-cyan-400">Rp 800.000</div>
            <p class="text-[11px] text-slate-500 leading-relaxed">Hak masuk turnstile resmi + akses VIP deck berkuota terkelola.</p>
        </div>

        <div class="p-4 bg-slate-900 rounded-xl border border-slate-800 space-y-2">
            <div class="text-slate-400 font-bold uppercase text-[10px]">Akomodasi Hotel Mitra (G7-K20)</div>
            <div class="text-lg font-black text-indigo-400">Rp 1.200.000</div>
            <p class="text-[11px] text-slate-500 leading-relaxed">Alokasi kamar Deluxe terverifikasi via validasi kamar real-time.</p>
        </div>

        <div class="p-4 bg-slate-900 rounded-xl border border-slate-800 space-y-2">
            <div class="text-slate-400 font-bold uppercase text-[10px]">Armada Transport Shuttle (G7-K21)</div>
            <div class="text-lg font-black text-amber-400">Rp 200.000</div>
            <p class="text-[11px] text-slate-500 leading-relaxed">Penjemputan bandara & shuttle berkala via modul Logistik terpadu.</p>
        </div>

        <div class="p-4 bg-emerald-950/40 rounded-xl border border-emerald-500/30 space-y-2">
            <div class="text-emerald-400 font-bold uppercase text-[10px]">Margin Bersih Gabungan</div>
            <div class="text-lg font-black text-emerald-300" x-text="'Rp ' + (finalPrice - totalFloorCost).toLocaleString('id-ID')"></div>
            <p class="text-[11px] text-emerald-500/80 leading-relaxed">Laba kotor operasional setelah seluruh floor cost mitra terpenuhi 100%.</p>
        </div>
    </div>
</div>
