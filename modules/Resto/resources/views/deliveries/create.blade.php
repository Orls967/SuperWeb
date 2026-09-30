<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('resto.deliveries.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-slate-900 transition-all">
                &larr;
            </a>
            <div>
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                    Buat Pesanan Delivery & Bungkus Baru
                </h2>
                <p class="text-sm text-slate-500 mt-0.5">Penetapan harga bungkus otomatis dan kalkulasi ongkir per radius km</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{
        distanceKm: 2.0,
        items: [{ menu_item_id: '', qty: 1 }],
        menuList: {{ Js::from($menuItems) }},
        
        getDeliveryFee() {
            let km = parseFloat(this.distanceKm) || 0;
            let fee = 10000;
            if (km > 3.0) {
                let extra = Math.ceil(km - 3.0);
                fee += extra * 2500;
            }
            return fee;
        },

        getTotalPortions() {
            return this.items.reduce((sum, it) => sum + (parseInt(it.qty) || 0), 0);
        },

        getPackagingFee() {
            return this.getTotalPortions() * 2000;
        },

        getFoodSubtotal() {
            let sub = 0;
            this.items.forEach(it => {
                let found = this.menuList.find(m => m.id == it.menu_item_id);
                if (found) {
                    let pr = found.takeaway_price || found.price;
                    sub += pr * (parseInt(it.qty) || 0);
                }
            });
            return sub;
        },

        getPB1() {
            return Math.round(this.getFoodSubtotal() * 0.10);
        },

        getGrandTotal() {
            let raw = this.getFoodSubtotal() + this.getPB1() + this.getDeliveryFee() + this.getPackagingFee();
            return Math.round(raw / 100) * 100;
        },

        addItem() {
            this.items.push({ menu_item_id: '', qty: 1 });
        },

        removeItem(idx) {
            if (this.items.length > 1) {
                this.items.splice(idx, 1);
            }
        }
    }">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('resto.deliveries.store') }}" class="space-y-6">
                @csrf

                <!-- Outlet & Recipient Information -->
                <div class="p-6 bg-white rounded-2xl shadow-sm border border-slate-200 space-y-4">
                    <h3 class="font-bold text-base text-slate-800 border-b border-slate-100 pb-3">Informasi Pengiriman & Tujuan</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Outlet Penyaji</label>
                            <select name="outlet_id" required class="w-full rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500 text-sm">
                                @foreach($outlets as $o)
                                    <option value="{{ $o->id }}">{{ $o->name }} ({{ $o->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Perkiraan Jarak (KM)</label>
                            <input type="number" step="0.1" min="0.1" max="100" name="distance_km" x-model="distanceKm" required class="w-full rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500 text-sm font-bold">
                            <span class="text-[11px] text-slate-400 mt-0.5 block">Biaya dasar: Rp 10.000 (1-3 km) + Rp 2.500 / km berikutnya</span>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Penerima</label>
                            <input type="text" name="recipient_name" placeholder="cth: Ibu Siti Rahmah" required class="w-full rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Nomor Telepon / WhatsApp</label>
                            <input type="text" name="recipient_phone" placeholder="cth: 081234567890" required class="w-full rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500 text-sm font-mono">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Alamat Lengkap Pengantaran</label>
                            <textarea name="delivery_address" rows="2" placeholder="Alamat detail, nomor rumah, patokan..." required class="w-full rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500 text-sm"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Menu Items List -->
                <div class="p-6 bg-white rounded-2xl shadow-sm border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="font-bold text-base text-slate-800">Daftar Menu yang Dipesan</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Harga otomatis menggunakan tarif bungkus (takeaway)</p>
                        </div>
                        <button type="button" @click="addItem()" class="px-3 py-1.5 bg-teal-50 text-teal-700 hover:bg-teal-100 font-bold text-xs rounded-lg transition-all">
                            + Tambah Menu
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(row, idx) in items" :key="idx">
                            <div class="p-3 bg-slate-50/70 rounded-xl border border-slate-200 flex flex-wrap items-center gap-3">
                                <div class="flex-1 min-w-[200px]">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-0.5">Pilihan Lauk / Menu</label>
                                    <select :name="'items['+idx+'][menu_item_id]'" x-model="row.menu_item_id" required class="w-full rounded-lg border-slate-300 text-xs">
                                        <option value="">-- Pilih Menu --</option>
                                        <template x-for="m in menuList" :key="m.id">
                                            <option :value="m.id" x-text="m.name + ' (Rp ' + Number(m.takeaway_price || m.price).toLocaleString('id-ID') + ')'"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="w-24">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-0.5">Porsi</label>
                                    <input type="number" :name="'items['+idx+'][qty]'" x-model="row.qty" min="1" max="100" required class="w-full rounded-lg border-slate-300 text-xs text-center font-bold">
                                </div>

                                <div class="pt-4">
                                    <button type="button" @click="removeItem(idx)" class="p-2 text-rose-500 hover:text-rose-700 font-bold text-sm" x-show="items.length > 1">&times;</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Payment Method & Summary -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="p-6 bg-white rounded-2xl shadow-sm border border-slate-200 space-y-4">
                        <h3 class="font-bold text-base text-slate-800 border-b border-slate-100 pb-3">Metode Pembayaran</h3>

                        <div class="space-y-2">
                            <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-all">
                                <input type="radio" name="payment_method" value="wallet" checked class="text-teal-600 focus:ring-teal-500">
                                <div>
                                    <div class="font-bold text-sm text-slate-800">Dompet Digital Superwebsite</div>
                                    <div class="text-xs text-slate-400">Pembayaran instan dipotong dari saldo akun Anda</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-all">
                                <input type="radio" name="payment_method" value="cash" class="text-teal-600 focus:ring-teal-500">
                                <div>
                                    <div class="font-bold text-sm text-slate-800">Tunai saat Antar (COD)</div>
                                    <div class="text-xs text-slate-400">Bayar tunai ke kurir saat makanan tiba</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Live Calculation Card -->
                    <div class="p-6 bg-slate-900 text-white rounded-2xl shadow-lg space-y-3">
                        <h4 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Ringkasan Tagihan</h4>

                        <div class="space-y-2 text-xs border-b border-slate-800 pb-3">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Subtotal Makanan:</span>
                                <span class="font-mono font-bold" x-text="'Rp ' + getFoodSubtotal().toLocaleString('id-ID')"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Pajak Restoran PB1 (10%):</span>
                                <span class="font-mono text-slate-300" x-text="'Rp ' + getPB1().toLocaleString('id-ID')"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Kemasan Nasi Bungkus:</span>
                                <span class="font-mono text-slate-300" x-text="'Rp ' + getPackagingFee().toLocaleString('id-ID')"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Ongkos Kirim (<span x-text="distanceKm"></span> km):</span>
                                <span class="font-mono text-teal-400 font-bold" x-text="'Rp ' + getDeliveryFee().toLocaleString('id-ID')"></span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <div>
                                <span class="text-xs text-slate-400">Total Pembayaran:</span>
                                <div class="text-2xl font-black text-emerald-400 font-mono" x-text="'Rp ' + getGrandTotal().toLocaleString('id-ID')"></div>
                            </div>

                            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-600 hover:to-emerald-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-teal-500/30 transition-all">
                                Konfirmasi & Kirim
                            </button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
