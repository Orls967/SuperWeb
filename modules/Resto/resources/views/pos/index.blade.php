<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="font-bold text-2xl text-slate-800 leading-tight flex items-center gap-2">
                    <span class="p-2 bg-gradient-to-tr from-amber-600 to-red-600 rounded-xl text-white shadow-md shadow-amber-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    </span>
                    <span>POS Kasir & Meja Hidang</span>
                </h2>
                <p class="text-sm text-slate-500 mt-1">Sistem kasir hidang khas Minang, sesi meja, etalase real-time & tutup kasir</p>
            </div>

            <!-- Outlet & Shift Status Bar -->
            <div class="flex items-center gap-3 flex-wrap">
                <form method="GET" action="{{ route('resto.pos.index') }}" class="flex items-center">
                    <select name="outlet_id" onchange="this.form.submit()" class="text-sm font-semibold rounded-xl border-slate-300 bg-white text-slate-700 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @foreach($allOutlets as $ot)
                            <option value="{{ $ot->id }}" {{ $outlet->id === $ot->id ? 'selected' : '' }}>
                                {{ $ot->name }} ({{ $ot->code }})
                            </option>
                        @endforeach
                    </select>
                </form>

                @if($activeShift)
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Shift #{{ $activeShift->id }} Aktif: Kas Awal Rp {{ number_format($activeShift->opening_float, 0, ',', '.') }}</span>
                    </div>
                @else
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        <span>Shift Kasir Belum Dibuka</span>
                    </div>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="posCashier({ outletId: {{ $outlet->id }} })">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Notifications Toast -->
            <div x-show="errorMessage" x-cloak class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span x-text="errorMessage"></span>
                </div>
                <button type="button" @click="errorMessage = ''" class="text-red-400 hover:text-red-600 font-bold">&times;</button>
            </div>

            <div x-show="successMessage" x-cloak class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span x-text="successMessage"></span>
                </div>
                <button type="button" @click="successMessage = ''" class="text-emerald-400 hover:text-emerald-600 font-bold">&times;</button>
            </div>

            <!-- Top Actions Bar -->
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200 flex flex-wrap items-center justify-between gap-4">
                <!-- Navigation Tabs -->
                <div class="flex items-center gap-2 p-1 bg-slate-100 rounded-xl text-xs sm:text-sm font-semibold text-slate-600">
                    <button type="button" @click="setTab('tables')" :class="activeTab === 'tables' ? 'bg-white text-slate-900 shadow-sm' : 'hover:text-slate-900'" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        <span>Denah Meja</span>
                    </button>
                    <button type="button" @click="setTab('hidang')" :class="activeTab === 'hidang' ? 'bg-white text-slate-900 shadow-sm' : 'hover:text-slate-900'" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <span>Saji Hidang</span>
                    </button>
                    <button type="button" @click="setTab('menu')" :class="activeTab === 'menu' ? 'bg-white text-slate-900 shadow-sm' : 'hover:text-slate-900'" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        <span>Pesan Menu</span>
                    </button>
                    <button type="button" @click="setTab('bill')" :class="activeTab === 'bill' ? 'bg-white text-slate-900 shadow-sm' : 'hover:text-slate-900'" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path></svg>
                        <span>Hitung Hidangan</span>
                    </button>
                </div>

                <!-- Shift Management Buttons -->
                <div class="flex items-center gap-2">
                    @if(!$activeShift)
                        <button type="button" @click="modalOpenShift = true" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs sm:text-sm font-semibold shadow-sm transition-all flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            <span>Buka Shift</span>
                        </button>
                    @else
                        <button type="button" @click="modalCloseShift = true" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs sm:text-sm font-semibold shadow-sm transition-all flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                            <span>Tutup Shift</span>
                        </button>
                        <button type="button" @click="modalSettleCash = true" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs sm:text-sm font-semibold shadow-sm transition-all flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            <span>Setor Kas Bank</span>
                        </button>
                    @endif
                </div>
            </div>

            <!-- Active Table Info Banner if selected -->
            <template x-if="selectedTable">
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-amber-600 text-white flex items-center justify-center font-black text-xl shadow-md shadow-amber-500/20" x-text="selectedTable.code"></div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-slate-800 text-base" x-text="'Meja ' + selectedTable.code + ' (' + selectedTable.zone + ')'"></h3>
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                      :class="selectedTable.status === 'available' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                      x-text="selectedTable.status.toUpperCase()"></span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5" x-text="selectedSession ? ('Sesi aktif: ' + selectedSession.guest_count + ' Tamu • Dibuka: ' + new Date(selectedSession.opened_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})) : 'Belum ada sesi aktif'"></p>
                        </div>
                    </div>

                    <!-- Quick Action: Tambuah (Nasi Putih) -->
                    @php
                        $nasiItem = \Modules\Resto\Domain\Models\MenuItem::where('name', 'like', '%Nasi Putih%')->first();
                    @endphp
                    <div class="flex items-center gap-2">
                        @if($nasiItem)
                            <button type="button" @click="quickTambahNasi({{ $nasiItem->id }})" class="px-3.5 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white rounded-xl text-xs font-bold shadow-md shadow-amber-500/20 transition-all flex items-center gap-1.5">
                                <span>🍚</span>
                                <span>+ Tambuah Nasi</span>
                            </button>
                        @endif

                        <template x-if="selectedOrder && selectedOrder.status === 'open'">
                            <button type="button" @click="submitCalculateBill()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-md shadow-red-500/20 transition-all flex items-center gap-1.5">
                                <span>Hitung Meja & Tutup</span>
                            </button>
                        </template>

                        @if(in_array(auth()->user()->role, ['admin', 'outlet_manager']))
                            <template x-if="selectedOrder">
                                <button type="button" @click="modalVoidOrder = true" class="px-3 py-2 bg-slate-200 hover:bg-red-100 text-slate-700 hover:text-red-700 rounded-xl text-xs font-semibold transition-all">
                                    Void Order
                                </button>
                            </template>
                        @endif
                    </div>
                </div>
            </template>

            <!-- TAB 1: DENAH MEJA -->
            <div x-show="activeTab === 'tables'" class="space-y-6">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-bold text-lg text-slate-800">Status Meja Makan (Resto Floor Plan)</h3>
                            <p class="text-xs text-slate-500">Klik meja untuk membuka sesi hidang atau melihat status pesanan aktif</p>
                        </div>
                        <div class="flex items-center gap-4 text-xs font-medium text-slate-500">
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-emerald-500"></span> Kosong (Tersedia)</span>
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-amber-500"></span> Terisi (Sedang Makan)</span>
                        </div>
                    </div>

                    <!-- Table Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        @foreach($tables as $table)
                            <div @click="selectTable({{ json_encode($table) }})"
                                 class="p-5 rounded-2xl border-2 cursor-pointer transition-all hover:scale-[1.02] flex flex-col justify-between h-40 {{ $table->status->value === 'available' ? 'bg-emerald-50/50 border-emerald-300 hover:border-emerald-500' : 'bg-amber-50/50 border-amber-300 hover:border-amber-500' }}">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <span class="font-black text-2xl text-slate-800">{{ $table->code }}</span>
                                        <p class="text-xs font-medium text-slate-500 mt-0.5 capitalize">{{ $table->zone }} • {{ $table->seats }} Kursi</p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $table->status->value === 'available' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $table->status->label() }}
                                    </span>
                                </div>

                                <div>
                                    @if($table->activeSession)
                                        <div class="pt-2 border-t border-slate-200/60">
                                            <div class="text-xs font-semibold text-slate-700 flex items-center justify-between">
                                                <span>{{ $table->activeSession->guest_count }} Tamu</span>
                                                <span class="text-amber-600 font-bold">Makan</span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 mt-0.5">{{ $table->activeSession->opened_at->format('H:i') }} WIB</p>
                                        </div>
                                    @else
                                        <div class="pt-2 border-t border-slate-200/60 flex items-center justify-center text-xs text-emerald-600 font-semibold">
                                            + Buka Meja
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- TAB 2: SAJI HIDANG (Pilih Piring dari Etalase) -->
            <div x-show="activeTab === 'hidang'" x-cloak class="space-y-6">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-bold text-lg text-slate-800">Sajikan Piring Hidang ke Meja</h3>
                            <p class="text-xs text-slate-500">Pilih piring etalase yang dibawa ke meja pelanggan (status Presented: belum dikenakan biaya sampai disentuh)</p>
                        </div>
                        <template x-if="selectedTrays.length > 0">
                            <button type="button" @click="submitPresentHidang()" class="px-4 py-2 bg-gradient-to-r from-amber-600 to-red-600 text-white rounded-xl text-xs font-bold shadow-md hover:from-amber-700 hover:to-red-700 transition-all flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span x-text="'Bawa ' + selectedTrays.length + ' Piring ke Meja'"></span>
                            </button>
                        </template>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        @forelse($displayTrays as $tray)
                            <div @click="toggleTraySelection({{ $tray->id }})"
                                 :class="selectedTrays.includes({{ $tray->id }}) ? 'ring-2 ring-amber-500 border-amber-500 bg-amber-50/30' : 'border-slate-200 hover:border-slate-300 bg-white'"
                                 class="p-4 rounded-xl border cursor-pointer transition-all flex flex-col justify-between">
                                <div>
                                    <div class="flex items-start justify-between gap-2">
                                        <h4 class="font-bold text-sm text-slate-800">{{ $tray->menuItem?->name }}</h4>
                                        <input type="checkbox" :checked="selectedTrays.includes({{ $tray->id }})" class="rounded text-amber-600 focus:ring-amber-500">
                                    </div>
                                    <p class="text-xs font-semibold text-amber-700 mt-1">Rp {{ number_format($tray->menuItem?->base_price ?? 0, 0, ',', '.') }}</p>
                                </div>
                                <div class="mt-4 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                                    <span>Tersisa: <strong class="text-slate-800">{{ $tray->portions_remaining }} porsi</strong></span>
                                    <span>Resirkulasi: {{ $tray->recirculation_count }}/3</span>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full py-12 text-center text-slate-400">
                                Belum ada piring masakan di etalase outlet. Silakan masak batch di dapur.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- TAB 3: PESAN MENU (Nasi, Minuman, Paket, Item Tambahan) -->
            <div x-show="activeTab === 'menu'" x-cloak class="space-y-6">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                        <div>
                            <h3 class="font-bold text-lg text-slate-800">Katalog Menu & Minuman Pesan Langsung</h3>
                            <p class="text-xs text-slate-500">Item yang langsung disiapkan saat dipesan (Nasi, Es Tebak, Teh Talua, Ayam Pop Panas)</p>
                        </div>

                        <!-- Category Filter Pills -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button type="button" @click="selectedCategory = 'all'" :class="selectedCategory === 'all' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">Semua</button>
                            @foreach($categories as $cat)
                                <button type="button" @click="selectedCategory = '{{ $cat->id }}'" :class="selectedCategory === '{{ $cat->id }}' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">{{ $cat->name }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                        @foreach($categories as $cat)
                            @foreach($cat->items as $item)
                                <div x-show="selectedCategory === 'all' || selectedCategory === '{{ $cat->id }}'"
                                     @click="addPesanItem({{ $item->id }})"
                                     class="p-4 rounded-xl border border-slate-200 hover:border-amber-400 hover:shadow-sm bg-white cursor-pointer transition-all flex flex-col justify-between">
                                    <div>
                                        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">{{ $cat->name }}</span>
                                        <h4 class="font-bold text-sm text-slate-800 mt-0.5 line-clamp-2">{{ $item->name }}</h4>
                                    </div>
                                    <div class="mt-3 flex items-center justify-between pt-2 border-t border-slate-100">
                                        <span class="text-xs font-black text-amber-600">Rp {{ number_format($item->base_price, 0, ',', '.') }}</span>
                                        <span class="text-xs text-slate-400">+ Tambah</span>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- TAB 4: HITUNG HIDANGAN & PEMBAYARAN -->
            <div x-show="activeTab === 'bill'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: List of Dishes with Consumed / Returned Toggle -->
                <div class="lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="font-bold text-lg text-slate-800">Layar Hitung Hidangan</h3>
                            <p class="text-xs text-slate-500">Tandai piring yang disentuh (Consumed: Dihitung) dan yang utuh (Returned: Kembali ke etalase)</p>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <template x-for="item in orderItems" :key="item.id">
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-slate-800" x-text="item.name_snapshot"></span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold"
                                              :class="item.source === 'hidang' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'"
                                              x-text="item.source.toUpperCase()"></span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5" x-text="'@ Rp ' + new Intl.NumberFormat('id-ID').format(item.unit_price_snapshot) + ' × ' + item.qty"></p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <!-- Toggle for Hidang items -->
                                    <template x-if="item.source === 'hidang'">
                                        <div class="flex items-center p-1 bg-slate-100 rounded-xl text-xs font-semibold">
                                            <button type="button" @click="consumedStatuses[item.id] = false"
                                                    :class="!consumedStatuses[item.id] ? 'bg-white text-slate-700 shadow-sm' : 'text-slate-400 hover:text-slate-600'"
                                                    class="px-2.5 py-1 rounded-lg transition-all">Utuh (Kembali)</button>
                                            <button type="button" @click="consumedStatuses[item.id] = true"
                                                    :class="consumedStatuses[item.id] ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-600'"
                                                    class="px-2.5 py-1 rounded-lg transition-all">Disentuh (Bayar)</button>
                                        </div>
                                    </template>

                                    <!-- Status for Pesan items (always consumed) -->
                                    <template x-if="item.source === 'pesan'">
                                        <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                            Dipesan
                                        </span>
                                    </template>

                                    <span class="font-bold text-sm text-slate-900 w-24 text-right"
                                          x-text="'Rp ' + new Intl.NumberFormat('id-ID').format((item.source === 'pesan' || consumedStatuses[item.id]) ? item.line_total : 0)"></span>
                                </div>
                            </div>
                        </template>

                        <template x-if="orderItems.length === 0">
                            <div class="py-8 text-center text-slate-400 text-sm">
                                Belum ada item masakan di meja ini. Silakan tambahkan hidang atau pesan menu.
                            </div>
                        </template>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                        <button type="button" @click="submitCalculateBill()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-all">
                            Perbarui Hitungan Tagihan
                        </button>
                    </div>
                </div>

                <!-- Right: Bill Summary & Payment Box -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 space-y-4 flex flex-col justify-between">
                    <div>
                        <h4 class="font-bold text-base text-slate-800 pb-3 border-b border-slate-100">Ringkasan Pembayaran</h4>

                        <div class="mt-4 space-y-2.5 text-sm">
                            <div class="flex justify-between text-slate-500">
                                <span>Subtotal Konsumsi</span>
                                <span class="font-semibold text-slate-800" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(selectedOrder ? selectedOrder.subtotal : 0)"></span>
                            </div>
                            <div class="flex justify-between text-slate-500">
                                <span>Pajak Restoran (PB1 10%)</span>
                                <span class="font-semibold text-slate-800" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(selectedOrder ? selectedOrder.tax_pb1 : 0)"></span>
                            </div>
                            <div class="flex justify-between text-slate-500">
                                <span>Pembulatan (Rp 100)</span>
                                <span class="font-semibold" :class="selectedOrder && selectedOrder.rounding < 0 ? 'text-red-500' : 'text-slate-800'" x-text="selectedOrder ? ((selectedOrder.rounding >= 0 ? '+' : '') + selectedOrder.rounding) : 0"></span>
                            </div>

                            <div class="pt-3 border-t border-slate-100 flex justify-between items-center text-lg font-black text-slate-900">
                                <span>Total Tagihan</span>
                                <span class="text-amber-600" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(selectedOrder ? selectedOrder.grand_total : 0)"></span>
                            </div>
                        </div>

                        <!-- Payment Method Selector -->
                        <div class="mt-6 space-y-3">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Metode Pembayaran</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" @click="paymentMethod = 'cash'"
                                        :class="paymentMethod === 'cash' ? 'border-amber-600 bg-amber-50 text-amber-900 font-bold' : 'border-slate-200 text-slate-600'"
                                        class="p-2.5 rounded-xl border text-xs text-center transition-all">
                                    💵 Uang Tunai
                                </button>
                                <button type="button" @click="paymentMethod = 'wallet'"
                                        :class="paymentMethod === 'wallet' ? 'border-amber-600 bg-amber-50 text-amber-900 font-bold' : 'border-slate-200 text-slate-600'"
                                        class="p-2.5 rounded-xl border text-xs text-center transition-all">
                                    💳 Saldo Wallet
                                </button>
                            </div>

                            <!-- Cash Input & Change -->
                            <template x-if="paymentMethod === 'cash'">
                                <div class="space-y-3 pt-2">
                                    <div>
                                        <label class="block text-xs text-slate-500 mb-1">Uang Tunai Diterima (Rp)</label>
                                        <input type="number" x-model.number="cashTendered" class="w-full text-base font-bold rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                                    </div>
                                    <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between text-xs">
                                        <span class="text-slate-500">Kembalian:</span>
                                        <span class="font-black text-sm text-slate-800"
                                              x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(Math.max(0, (cashTendered || 0) - (selectedOrder ? selectedOrder.grand_total : 0)))"></span>
                                    </div>
                                </div>
                            </template>

                            <!-- Wallet PIN Input -->
                            <template x-if="paymentMethod === 'wallet'">
                                <div class="space-y-2 pt-2">
                                    <label class="block text-xs text-slate-500">PIN Wallet Pelanggan (6 Angka)</label>
                                    <input type="password" maxlength="6" x-model="walletPin" placeholder="******" class="w-full text-center tracking-widest text-lg font-bold rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <button type="button" @click="submitPayment()"
                                :disabled="loading || !selectedOrder || selectedOrder.grand_total <= 0"
                                class="w-full py-3 bg-gradient-to-r from-amber-600 to-red-600 hover:from-amber-700 hover:to-red-700 disabled:opacity-50 text-white rounded-xl font-bold text-sm shadow-md shadow-amber-500/20 transition-all flex items-center justify-center gap-2">
                            <span x-show="loading" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                            <span>Proses Pembayaran & Cetak Struk</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- MODAL 1: BUKA SESI MEJA -->
            <div x-show="modalOpenSession" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-100 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-lg text-slate-800" x-text="selectedTable ? ('Buka Sesi Meja ' + selectedTable.code) : 'Buka Sesi Meja'"></h3>
                        <button type="button" @click="modalOpenSession = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Jumlah Tamu</label>
                            <input type="number" min="1" x-model.number="guestCount" class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Pelanggan (Opsional)</label>
                            <input type="text" x-model="guestName" placeholder="Tamu Meja" class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm">
                        </div>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-2">
                        <button type="button" @click="modalOpenSession = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                        <button type="button" @click="submitOpenSession()" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-all">Buka Sesi</button>
                    </div>
                </div>
            </div>

            <!-- MODAL 2: BUKA SHIFT KASIR -->
            <div x-show="modalOpenShift" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-100 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-lg text-slate-800">Buka Shift Kasir Baru</h3>
                        <button type="button" @click="modalOpenShift = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('resto.pos.shift.open') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Modal Kas Fisik Awal (Opening Float)</label>
                            <input type="number" name="opening_float" required min="0" placeholder="500000" class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm font-semibold">
                            <p class="text-[11px] text-slate-400 mt-1">Uang tunai pecahan kecil di laci kasir saat awal shift</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Shift</label>
                            <input type="text" name="note" placeholder="Shift pagi" class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm">
                        </div>

                        <div class="pt-3 flex items-center justify-end gap-2">
                            <button type="button" @click="modalOpenShift = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all">Buka Shift Sekarang</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL 3: TUTUP SHIFT KASIR -->
            @if($activeShift)
                <div x-show="modalCloseShift" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-100 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-lg text-slate-800">Tutup Shift Kasir #{{ $activeShift->id }}</h3>
                            <button type="button" @click="modalCloseShift = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                        </div>

                        <form method="POST" action="{{ route('resto.pos.shift.close') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="shift_id" value="{{ $activeShift->id }}">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Hasil Hitungan Fisik Kas Tunai (Counted Cash)</label>
                                <input type="number" name="counted_cash" required min="0" placeholder="Jumlah fisik uang di laci" class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm font-semibold">
                                <p class="text-[11px] text-slate-400 mt-1">Sistem akan menghitung selisih kas (lebih/kurang) dan memposting ke ledger secara otomatis</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Penutupan</label>
                                <input type="text" name="note" placeholder="Kas serah terima lancar" class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm">
                            </div>

                            <div class="pt-3 flex items-center justify-end gap-2">
                                <button type="button" @click="modalCloseShift = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                                <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-all">Tutup Shift & Rekonsiliasi</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <!-- MODAL 4: SETOR KAS KE BANK -->
            <div x-show="modalSettleCash" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-100 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-lg text-slate-800">Setoran Kas Laci ke Bank</h3>
                        <button type="button" @click="modalSettleCash = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('resto.pos.shift.settle') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Nominal Uang Tunai Disetor (Rp)</label>
                            <input type="number" name="amount" required min="1" placeholder="5000000" class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm font-semibold">
                            <p class="text-[11px] text-slate-400 mt-1">Akan memindahkan saldo dari cash:drawer ke rekening kliring bank (clearing:external)</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan / Bukti Setor</label>
                            <input type="text" name="note" placeholder="Setoran via Bank Mandiri slip #1234" class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 text-sm">
                        </div>

                        <div class="pt-3 flex items-center justify-end gap-2">
                            <button type="button" @click="modalSettleCash = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all">Catat Setoran Bank</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL 5: VOID ORDER -->
            <div x-show="modalVoidOrder" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-100 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-lg text-red-600">Batalkan Pesanan (VOID)</h3>
                        <button type="button" @click="modalVoidOrder = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <p class="text-xs text-slate-500">Tindakan ini memerlukan otorisasi Manager dan akan membalik pembukuan kas/wallet di ledger jika pesanan sudah dibayar.</p>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Alasan Pembatalan / Void</label>
                        <textarea x-model="voidReason" rows="3" placeholder="Salah input meja / pelanggan batal makan..." class="w-full rounded-xl border-slate-300 focus:border-red-500 focus:ring-red-500 text-sm"></textarea>
                    </div>

                    <div class="pt-3 flex items-center justify-end gap-2">
                        <button type="button" @click="modalVoidOrder = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                        <button type="button" @click="submitVoidOrder()" :disabled="!voidReason" class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition-all">Konfirmasi VOID</button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
