<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('resto.catering.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-slate-900 transition-all">
                &larr;
            </a>
            <div>
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                    Buat Penawaran Katering Baru
                </h2>
                <p class="text-sm text-slate-500 mt-0.5">Penetapan kuota pax, validasi kapasitas dapur, dan kalkulasi deposit 30%</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{
        pax: 50,
        selectedPackageId: '{{ $packages->first()?->id ?? '' }}',
        packages: {{ Js::from($packages) }},
        
        getPricePerPax() {
            let pkg = this.packages.find(p => p.id == this.selectedPackageId);
            return pkg ? pkg.price_per_pax : 35000;
        },

        getSubtotal() {
            return (parseInt(this.pax) || 0) * this.getPricePerPax();
        },

        getDeliveryFee() {
            return 50000;
        },

        getGrandTotal() {
            return this.getSubtotal() + this.getDeliveryFee();
        },

        getDeposit() {
            return Math.round(this.getGrandTotal() * 0.30);
        }
    }">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('resto.catering.store') }}" class="space-y-6">
                @csrf

                <!-- Detail Acara & Pemesan -->
                <div class="p-6 bg-white rounded-2xl shadow-sm border border-slate-200 space-y-4">
                    <h3 class="font-bold text-base text-slate-800 border-b border-slate-100 pb-3">Informasi Acara & Pemesan</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Outlet Dapur Pelaksana</label>
                            <select name="outlet_id" required class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm">
                                @foreach($outlets as $o)
                                    <option value="{{ $o->id }}">{{ $o->name }} (Maks: {{ $o->type->value === 'central_kitchen' ? '2.000' : '500' }} pax/hari)</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Paket Katering Pilihan</label>
                            <select name="package_id" x-model="selectedPackageId" class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm">
                                <option value="">-- Pilihan Custom (Rp 35.000/pax) --</option>
                                @foreach($packages as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} (Rp {{ number_format($p->price_per_pax, 0, ',', '.') }}/pax)</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Pemesan / Instansi</label>
                            <input type="text" name="customer_name" placeholder="cth: Bapak Hendra / PT Karya Bersama" required class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Nomor Telepon / WhatsApp</label>
                            <input type="text" name="customer_phone" placeholder="cth: 081122334455" required class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm font-mono">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Tanggal Acara</label>
                            <input type="date" name="event_date" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d', strtotime('+3 days')) }}" required class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Waktu Pengantaran / Acara</label>
                            <input type="time" name="event_time" value="11:30" required class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Jumlah Porsi (Pax)</label>
                            <input type="number" name="pax" x-model="pax" min="10" max="2000" required class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm font-bold">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Tambahan (Opsional)</label>
                            <input type="text" name="notes" placeholder="cth: Sambal dipisah, kemasan kotak bento" class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Alamat Lengkap Lokasi Acara</label>
                            <textarea name="delivery_address" rows="2" placeholder="Gedung pertemuan, lantai, nama ruangan..." required class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Live Summary & Deposit Calculation -->
                <div class="p-6 bg-slate-900 text-white rounded-2xl shadow-xl space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <h4 class="text-xs font-bold text-amber-400 uppercase tracking-wider">Kalkulasi Penawaran & Deposit 30%</h4>
                        <span class="text-xs text-slate-400">Pembayaran bertahap via Payment Hub</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div>
                            <span class="text-slate-400 block">Harga per Pax:</span>
                            <span class="font-mono text-base font-bold text-white mt-1 block" x-text="'Rp ' + getPricePerPax().toLocaleString('id-ID')"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Subtotal Makanan:</span>
                            <span class="font-mono text-base font-bold text-white mt-1 block" x-text="'Rp ' + getSubtotal().toLocaleString('id-ID')"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Ongkos Logistik:</span>
                            <span class="font-mono text-base font-bold text-slate-300 mt-1 block" x-text="'Rp ' + getDeliveryFee().toLocaleString('id-ID')"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Total Tagihan (100%):</span>
                            <span class="font-mono text-lg font-black text-amber-400 mt-1 block" x-text="'Rp ' + getGrandTotal().toLocaleString('id-ID')"></span>
                        </div>
                    </div>

                    <div class="p-4 bg-amber-950/40 border border-amber-500/30 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-xs font-semibold text-amber-300 block">Deposit Awal 30% yang akan Ditahan di Rekening Bersama (Escrow):</span>
                            <span class="text-2xl font-black text-emerald-400 font-mono" x-text="'Rp ' + getDeposit().toLocaleString('id-ID')"></span>
                        </div>

                        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-amber-500/30 transition-all">
                            Buat Penawaran Katering
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
