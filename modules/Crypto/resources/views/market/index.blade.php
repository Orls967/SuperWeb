<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold bg-gradient-to-r from-amber-400 via-orange-400 to-yellow-500 bg-clip-text text-transparent flex items-center gap-2">
                    <svg class="w-7 h-7 text-amber-400 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    Crypto Exchange & Tracker
                </h2>
                <p class="text-sm text-gray-400 mt-1">Simulasi perdagangan aset kripto global dengan eksekusi pasar real-time & integrasi double-entry ledger.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('crypto.portfolio.index') }}" class="px-4 py-2 bg-indigo-600/30 hover:bg-indigo-600/50 border border-indigo-500/40 rounded-xl text-indigo-200 text-sm font-semibold flex items-center gap-2 transition duration-200 shadow-lg shadow-indigo-500/10">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Portofolio Saya
                </a>
                <a href="{{ route('crypto.alerts.index') }}" class="px-4 py-2 bg-gray-800/80 hover:bg-gray-700/80 border border-gray-700/60 rounded-xl text-gray-300 text-sm font-semibold flex items-center gap-2 transition duration-200">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    Price Alerts
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="cryptoMarketData()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Live Status Ticker Banner -->
            <div class="bg-gray-900/60 border border-gray-800/80 backdrop-blur-xl rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <span class="text-xs font-mono font-medium text-emerald-400 uppercase tracking-wider">Feed Pasar Aktif (Tick Otomatis)</span>
                    <span class="text-xs text-gray-500">• Auto-refresh setiap 30 detik</span>
                </div>
                <div class="text-xs text-gray-400 font-mono">
                    Pembaruan terakhir: <span x-text="lastUpdated" class="text-gray-200">Baru saja</span>
                </div>
            </div>

            <!-- Market Assets Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($marketData as $coin)
                    <div class="bg-gray-900/80 border border-gray-800/80 hover:border-gray-700/80 rounded-2xl p-6 backdrop-blur-xl transition duration-300 hover:shadow-2xl hover:shadow-amber-500/5 group flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl flex items-center justify-center font-bold text-lg
                                        @if($coin['symbol'] === 'BTC') bg-amber-500/20 text-amber-400 border border-amber-500/30
                                        @elseif($coin['symbol'] === 'ETH') bg-indigo-500/20 text-indigo-400 border border-indigo-500/30
                                        @elseif($coin['symbol'] === 'SOL') bg-emerald-500/20 text-emerald-400 border border-emerald-500/30
                                        @elseif($coin['symbol'] === 'BNB') bg-yellow-500/20 text-yellow-400 border border-yellow-500/30
                                        @else bg-teal-500/20 text-teal-400 border border-teal-500/30 @endif">
                                        {{ $coin['symbol'] }}
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-white group-hover:text-amber-400 transition">{{ $coin['name'] }}</h3>
                                        <span class="text-xs font-mono text-gray-400 uppercase">{{ $coin['symbol'] }} / IDR</span>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold font-mono flex items-center gap-1
                                    @if($coin['change_24h'] >= 0) bg-emerald-500/10 text-emerald-400 border border-emerald-500/20
                                    @else bg-rose-500/10 text-rose-400 border border-rose-500/20 @endif"
                                    x-text="formatChange('{{ $coin['symbol'] }}', {{ $coin['change_24h'] }})">
                                    {{ $coin['change_24h'] >= 0 ? '+' : '' }}{{ $coin['change_24h'] }}%
                                </span>
                            </div>

                            <div class="mt-6">
                                <div class="text-xs text-gray-400">Harga Terkini</div>
                                <div class="text-2xl font-black text-white font-mono mt-1">
                                    Rp <span x-text="prices['{{ $coin['symbol'] }}']?.price_formatted || '{{ $coin['current_price_formatted'] }}'">{{ $coin['current_price_formatted'] }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-gray-800/80 flex items-center justify-between gap-3">
                            <a href="{{ route('crypto.market.show', $coin['symbol']) }}" class="text-xs text-gray-400 hover:text-white flex items-center gap-1 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                                </svg>
                                Grafik 24J/7H
                            </a>
                            <div class="flex items-center gap-2">
                                <button @click="openTradeModal('{{ $coin['symbol'] }}', 'buy')" class="px-3 py-1.5 bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-400 rounded-lg text-xs font-semibold transition">
                                    Beli
                                </button>
                                <button @click="openTradeModal('{{ $coin['symbol'] }}', 'sell')" class="px-3 py-1.5 bg-rose-500/20 hover:bg-rose-500/30 border border-rose-500/40 text-rose-400 rounded-lg text-xs font-semibold transition">
                                    Jual
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Fast Trade Modal (Alpine.js) -->
            <div x-show="tradeModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="tradeModalOpen" x-transition.opacity class="fixed inset-0 bg-black/80 backdrop-blur-md transition-opacity" @click="closeTradeModal()"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div x-show="tradeModalOpen" x-transition class="inline-block align-bottom bg-gray-900 border border-gray-800 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6 space-y-6">
                        
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between border-b border-gray-800 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/30 text-amber-400 font-bold flex items-center justify-center" x-text="selectedSymbol">
                                    BTC
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white">Eksekusi Trade Cepat</h3>
                                    <p class="text-xs text-gray-400">Order instan dengan kuotasi terkunci 15 detik</p>
                                </div>
                            </div>
                            <button @click="closeTradeModal()" class="text-gray-400 hover:text-white p-1 rounded-lg">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Side Toggle: Buy / Sell -->
                        <div class="grid grid-cols-2 gap-2 p-1 bg-gray-800/80 rounded-xl">
                            <button type="button" @click="setSide('buy')" :class="tradeSide === 'buy' ? 'bg-emerald-600 text-white font-bold shadow-lg' : 'text-gray-400 hover:text-white'" class="py-2.5 rounded-lg text-sm transition">
                                Beli (Buy)
                            </button>
                            <button type="button" @click="setSide('sell')" :class="tradeSide === 'sell' ? 'bg-rose-600 text-white font-bold shadow-lg' : 'text-gray-400 hover:text-white'" class="py-2.5 rounded-lg text-sm transition">
                                Jual (Sell)
                            </button>
                        </div>

                        <!-- Step 1: Input Nominal / Quantity -->
                        <div x-show="!activeQuote && !tradeSuccess" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Nominal Rupiah (IDR)</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3 text-sm font-semibold text-gray-500">Rp</span>
                                    <input type="number" x-model="amountIdr" @input="onIdrInput()" placeholder="0" class="w-full bg-gray-800 border border-gray-700 rounded-xl pl-12 pr-4 py-2.5 text-white font-mono text-sm focus:border-amber-500 focus:ring focus:ring-amber-500/20">
                                </div>
                            </div>

                            <div class="flex items-center justify-center my-1 text-gray-500 text-xs">
                                <span>&mdash; ATAU &mdash;</span>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Jumlah Koin (<span x-text="selectedSymbol"></span>)</label>
                                <div class="relative">
                                    <input type="number" step="any" x-model="cryptoQty" @input="onCryptoInput()" placeholder="0.00000000" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2.5 text-white font-mono text-sm focus:border-amber-500 focus:ring focus:ring-amber-500/20">
                                    <span class="absolute right-4 top-2.5 text-xs font-bold text-gray-400 uppercase" x-text="selectedSymbol"></span>
                                </div>
                            </div>

                            <div class="bg-gray-800/40 rounded-xl p-3 border border-gray-700/50 flex items-center justify-between text-xs text-gray-400">
                                <span>Estimasi Biaya Transaksi (0.2%)</span>
                                <span class="font-mono text-gray-300">Termasuk dalam kuotasi</span>
                            </div>

                            <div x-show="errorMessage" class="p-3 bg-rose-500/20 border border-rose-500/40 rounded-xl text-xs text-rose-300" x-text="errorMessage"></div>

                            <button type="button" @click="requestQuote()" :disabled="loadingQuote" class="w-full py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold rounded-xl text-sm transition duration-200 shadow-lg shadow-amber-500/20 disabled:opacity-50">
                                <span x-show="!loadingQuote">Dapatkan Kuotasi Harga (15s)</span>
                                <span x-show="loadingQuote">Memproses Kuotasi...</span>
                            </button>
                        </div>

                        <!-- Step 2: Quote Confirmation & PIN Verification (Active Quote with 15s Timer) -->
                        <div x-show="activeQuote && !tradeSuccess" class="space-y-4">
                            <!-- Countdown Timer Bar -->
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-amber-400 font-semibold flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        Kuotasi Terkunci: <span x-text="countdown" class="font-mono font-bold">15</span> detik
                                    </span>
                                    <span class="text-gray-400 text-2xs">Fee 0.2%</span>
                                </div>
                                <div class="w-full bg-gray-800 rounded-full h-2 overflow-hidden">
                                    <div class="bg-amber-400 h-2 transition-all duration-1000 ease-linear" :style="`width: ${(countdown / 15) * 100}%`"></div>
                                </div>
                            </div>

                            <!-- Quote Summary Table -->
                            <div class="bg-gray-800/60 rounded-xl p-4 border border-gray-700 space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Aset & Tipe:</span>
                                    <span class="font-bold text-white font-mono" x-text="`${activeQuote?.side.toUpperCase()} ${activeQuote?.symbol}`"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Harga Per Koin:</span>
                                    <span class="font-mono text-white" x-text="`Rp ${activeQuote?.price_formatted}`"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Jumlah Koin:</span>
                                    <span class="font-mono text-emerald-400 font-bold" x-text="`${activeQuote?.quantity} ${activeQuote?.symbol}`"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Subtotal:</span>
                                    <span class="font-mono text-white" x-text="`Rp ${activeQuote?.gross_formatted}`"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Biaya Exchange (0.2%):</span>
                                    <span class="font-mono text-amber-400" x-text="`Rp ${activeQuote?.fee_formatted}`"></span>
                                </div>
                                <div class="border-t border-gray-700 pt-2 flex justify-between font-bold">
                                    <span class="text-white" x-text="activeQuote?.side === 'buy' ? 'Total Dipotong (IDR):' : 'Total Diterima (IDR):'"></span>
                                    <span class="font-mono text-lg text-amber-400" x-text="`Rp ${formatIdr(activeQuote?.total_idr)}`"></span>
                                </div>
                            </div>

                            <!-- 6-Digit PIN Prompt -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Masukkan 6 Digit PIN Dompet</label>
                                <input type="password" maxlength="6" x-model="tradePin" placeholder="••••••" class="w-full text-center tracking-[1em] text-lg bg-gray-800 border border-gray-700 rounded-xl py-2.5 text-white font-mono focus:border-amber-500 focus:ring focus:ring-amber-500/20">
                            </div>

                            <div x-show="errorMessage" class="p-3 bg-rose-500/20 border border-rose-500/40 rounded-xl text-xs text-rose-300" x-text="errorMessage"></div>

                            <div class="flex gap-3">
                                <button type="button" @click="cancelQuote()" class="w-1/3 py-2.5 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl text-sm font-semibold transition">
                                    Batal
                                </button>
                                <button type="button" @click="executeTrade()" :disabled="executingTrade || countdown <= 0 || tradePin.length !== 6" class="w-2/3 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold rounded-xl text-sm transition duration-200 shadow-lg shadow-emerald-500/20 disabled:opacity-50">
                                    <span x-show="!executingTrade">Konfirmasi Transaksi</span>
                                    <span x-show="executingTrade">Mengeksekusi Ledger...</span>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Success Receipt -->
                        <div x-show="tradeSuccess" class="space-y-4 text-center py-4">
                            <div class="w-16 h-16 bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 rounded-full flex items-center justify-center mx-auto">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <h4 class="text-xl font-bold text-white">Transaksi Berhasil!</h4>
                            <p class="text-xs text-gray-400" x-text="successMessage"></p>

                            <div class="bg-gray-800/60 rounded-xl p-4 text-left font-mono text-xs space-y-2 border border-gray-700">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Order ID:</span>
                                    <span class="text-white truncate max-w-[200px]" x-text="lastTradeResult?.uuid"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Tipe:</span>
                                    <span class="text-emerald-400 font-bold" x-text="lastTradeResult?.side_label"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Jumlah:</span>
                                    <span class="text-white" x-text="`${lastTradeResult?.quantity} ${lastTradeResult?.symbol}`"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Total Nilai:</span>
                                    <span class="text-amber-400 font-bold" x-text="`Rp ${lastTradeResult?.gross_formatted}`"></span>
                                </div>
                            </div>

                            <div class="flex gap-3 pt-2">
                                <a href="{{ route('crypto.portfolio.index') }}" class="w-1/2 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold transition text-center">
                                    Lihat Portofolio
                                </a>
                                <button type="button" @click="closeTradeModal()" class="w-1/2 py-2.5 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl text-sm font-semibold transition">
                                    Selesai
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function cryptoMarketData() {
            return {
                prices: {},
                lastUpdated: 'Baru saja',
                tradeModalOpen: false,
                selectedSymbol: 'BTC',
                tradeSide: 'buy',
                amountIdr: '',
                cryptoQty: '',
                loadingQuote: false,
                activeQuote: null,
                countdown: 15,
                timerInterval: null,
                tradePin: '',
                executingTrade: false,
                tradeSuccess: false,
                successMessage: '',
                lastTradeResult: null,
                errorMessage: '',

                init() {
                    this.pollPrices();
                    setInterval(() => {
                        this.pollPrices();
                    }, 30000); // Poll every 30s
                },

                async pollPrices() {
                    try {
                        const res = await fetch('{{ route('crypto.api.prices') }}');
                        const data = await res.json();
                        if (data.status === 'success') {
                            this.prices = data.prices;
                            this.lastUpdated = new Date().toLocaleTimeString('id-ID');
                        }
                    } catch (e) {
                        console.error('Failed to poll crypto prices:', e);
                    }
                },

                formatChange(symbol, defaultChange) {
                    if (this.prices[symbol]) {
                        const ch = this.prices[symbol].change_24h;
                        return (ch >= 0 ? '+' : '') + ch + '%';
                    }
                    return (defaultChange >= 0 ? '+' : '') + defaultChange + '%';
                },

                formatIdr(val) {
                    if (!val) return '0';
                    return Math.round(Number(val)).toLocaleString('id-ID');
                },

                openTradeModal(symbol, side) {
                    @if(!auth()->check())
                        window.location.href = "{{ route('login') }}";
                        return;
                    @endif

                    this.selectedSymbol = symbol;
                    this.tradeSide = side;
                    this.amountIdr = '';
                    this.cryptoQty = '';
                    this.activeQuote = null;
                    this.tradeSuccess = false;
                    this.tradePin = '';
                    this.errorMessage = '';
                    this.tradeModalOpen = true;
                },

                closeTradeModal() {
                    this.tradeModalOpen = false;
                    this.cancelQuote();
                },

                setSide(side) {
                    this.tradeSide = side;
                    this.cancelQuote();
                },

                onIdrInput() {
                    this.cryptoQty = '';
                },

                onCryptoInput() {
                    this.amountIdr = '';
                },

                async requestQuote() {
                    this.errorMessage = '';
                    this.loadingQuote = true;

                    try {
                        const res = await fetch('{{ route('crypto.trade.quote') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                symbol: this.selectedSymbol,
                                side: this.tradeSide,
                                amount_idr: this.amountIdr ? this.amountIdr : null,
                                crypto_qty: this.cryptoQty ? this.cryptoQty : null
                            })
                        });

                        const data = await res.json();

                        if (!res.ok || !data.success) {
                            this.errorMessage = data.message || 'Gagal memperoleh kuotasi harga.';
                            this.loadingQuote = false;
                            return;
                        }

                        this.activeQuote = data.quote;
                        this.countdown = 15;
                        this.startCountdown();
                    } catch (e) {
                        this.errorMessage = 'Terjadi kesalahan jaringan.';
                    } finally {
                        this.loadingQuote = false;
                    }
                },

                startCountdown() {
                    clearInterval(this.timerInterval);
                    this.timerInterval = setInterval(() => {
                        this.countdown--;
                        if (this.countdown <= 0) {
                            clearInterval(this.timerInterval);
                            this.errorMessage = 'Kuotasi telah kedaluwarsa (15 detik). Silakan minta kuotasi baru.';
                            this.activeQuote = null;
                        }
                    }, 1000);
                },

                cancelQuote() {
                    clearInterval(this.timerInterval);
                    this.activeQuote = null;
                    this.tradePin = '';
                    this.errorMessage = '';
                },

                async executeTrade() {
                    this.errorMessage = '';
                    this.executingTrade = true;

                    try {
                        const res = await fetch('{{ route('crypto.trade.execute') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                quote_uuid: this.activeQuote.uuid,
                                pin: this.tradePin
                            })
                        });

                        const data = await res.json();

                        if (!res.ok || !data.success) {
                            this.errorMessage = data.message || 'Eksekusi transaksi gagal.';
                            this.executingTrade = false;
                            return;
                        }

                        clearInterval(this.timerInterval);
                        this.lastTradeResult = data.trade;
                        this.successMessage = data.message;
                        this.tradeSuccess = true;
                    } catch (e) {
                        this.errorMessage = 'Gagal memproses transaksi kripto.';
                    } finally {
                        this.executingTrade = false;
                    }
                }
            };
        }
    </script>
</x-app-layout>
