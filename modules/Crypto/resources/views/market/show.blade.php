<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('crypto.market.index') }}" class="p-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-bold text-white">{{ $asset->name }}</h2>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-gray-800 text-amber-400 border border-gray-700">{{ $asset->symbol }}</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">Analisis pasar real-time & perdagangan instan</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <div class="text-2xl font-black text-white font-mono">
                        Rp <span id="assetCurrentPrice">{{ $currentPriceFormatted }}</span>
                    </div>
                    <div class="text-xs font-mono font-semibold {{ $change24h >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $change24h >= 0 ? '+' : '' }}{{ $change24h }}% (24 Jam)
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="py-8" x-data="cryptoCoinView('{{ $asset->symbol }}', {{ json_encode($chartData) }})">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Left 2 Cols: Chart & History -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Chart Card -->
                    <div class="bg-gray-900/80 border border-gray-800/80 rounded-3xl p-6 backdrop-blur-xl space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-800/80 pb-4">
                            <div>
                                <h3 class="text-base font-bold text-white">Grafik Pergerakan Harga</h3>
                                <p class="text-xs text-gray-400">Harga dalam Rupiah (IDR)</p>
                            </div>
                            <!-- Range Buttons -->
                            <div class="flex items-center gap-1 bg-gray-800/80 p-1 rounded-xl">
                                <button type="button" @click="changeRange('24h')" :class="range === '24h' ? 'bg-amber-500 text-black font-bold shadow' : 'text-gray-400 hover:text-white'" class="px-3 py-1 rounded-lg text-xs transition">
                                    24 Jam
                                </button>
                                <button type="button" @click="changeRange('7d')" :class="range === '7d' ? 'bg-amber-500 text-black font-bold shadow' : 'text-gray-400 hover:text-white'" class="px-3 py-1 rounded-lg text-xs transition">
                                    7 Hari
                                </button>
                                <button type="button" @click="changeRange('30d')" :class="range === '30d' ? 'bg-amber-500 text-black font-bold shadow' : 'text-gray-400 hover:text-white'" class="px-3 py-1 rounded-lg text-xs transition">
                                    30 Hari
                                </button>
                            </div>
                        </div>

                        <!-- Canvas for Chart.js -->
                        <div class="relative h-80 w-full">
                            <canvas id="cryptoPriceChart"></canvas>
                        </div>
                    </div>

                    <!-- Market Info & Price Alert Box -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-gray-900/80 border border-gray-800/80 rounded-2xl p-5 space-y-3">
                            <h4 class="text-sm font-bold text-white">Tentang {{ $asset->name }} ({{ $asset->symbol }})</h4>
                            <div class="text-xs text-gray-400 space-y-2 font-mono">
                                <div class="flex justify-between">
                                    <span>Simbol:</span>
                                    <span class="text-white">{{ $asset->symbol }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Desimal Jaringan:</span>
                                    <span class="text-white">{{ $asset->decimals }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Volatilitas Simulasi:</span>
                                    <span class="text-white">{{ ($asset->volatility * 100) }}%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Saldo Koin Anda:</span>
                                    <span class="text-emerald-400 font-bold">{{ $userHolding }} {{ $asset->symbol }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Saldo IDR Anda:</span>
                                    <span class="text-amber-400 font-bold">Rp {{ number_format((float) $userIdrBalance, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Price Alert -->
                        <div class="bg-gray-900/80 border border-gray-800/80 rounded-2xl p-5 space-y-3">
                            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                Pasang Price Alert
                            </h4>
                            <p class="text-xs text-gray-400">Dapatkan notifikasi jika harga mencapai target yang Anda tetapkan.</p>
                            @auth
                                <form action="{{ route('crypto.alerts.store') }}" method="POST" class="space-y-2">
                                    @csrf
                                    <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                                    <div class="grid grid-cols-2 gap-2">
                                        <select name="condition" class="bg-gray-800 border border-gray-700 text-white rounded-xl text-xs py-2 px-3 focus:ring-amber-500">
                                            <option value="above">Naik di atas (>=)</option>
                                            <option value="below">Turun di bawah (<=)</option>
                                        </select>
                                        <input type="number" name="target_price_idr" placeholder="Harga Target (Rp)" required class="bg-gray-800 border border-gray-700 text-white rounded-xl text-xs py-2 px-3 focus:ring-amber-500 font-mono">
                                    </div>
                                    <button type="submit" class="w-full py-2 bg-gray-800 hover:bg-gray-700 text-amber-400 border border-amber-500/30 font-semibold rounded-xl text-xs transition">
                                        Simpan Pengingat
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="block text-center py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl text-xs transition">
                                    Masuk untuk Memasang Alert
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>

                <!-- Right Col: Trade Execution Box -->
                <div class="space-y-6">
                    <div class="bg-gray-900/80 border border-gray-800/80 rounded-3xl p-6 backdrop-blur-xl space-y-5 shadow-xl">
                        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                            <h3 class="text-base font-bold text-white">Beli & Jual Instan</h3>
                            <span class="text-xs font-mono text-gray-400">Biaya 0.2%</span>
                        </div>

                        <!-- Trade Side Switcher -->
                        <div class="grid grid-cols-2 gap-2 p-1 bg-gray-800/80 rounded-xl">
                            <button type="button" @click="setSide('buy')" :class="tradeSide === 'buy' ? 'bg-emerald-600 text-white font-bold shadow' : 'text-gray-400 hover:text-white'" class="py-2 rounded-lg text-xs transition">
                                Beli {{ $asset->symbol }}
                            </button>
                            <button type="button" @click="setSide('sell')" :class="tradeSide === 'sell' ? 'bg-rose-600 text-white font-bold shadow' : 'text-gray-400 hover:text-white'" class="py-2 rounded-lg text-xs transition">
                                Jual {{ $asset->symbol }}
                            </button>
                        </div>

                        <!-- Trade Inputs (Step 1) -->
                        <div x-show="!activeQuote && !tradeSuccess" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Nominal Rupiah (IDR)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-xs font-bold text-gray-500">Rp</span>
                                    <input type="number" x-model="amountIdr" @input="onIdrInput()" placeholder="0" class="w-full bg-gray-800 border border-gray-700 rounded-xl pl-10 pr-4 py-2 text-white font-mono text-sm focus:ring-amber-500">
                                </div>
                            </div>

                            <div class="flex items-center justify-center my-0.5 text-gray-500 text-xs">
                                <span>&mdash; ATAU &mdash;</span>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Jumlah Koin ({{ $asset->symbol }})</label>
                                <div class="relative">
                                    <input type="number" step="any" x-model="cryptoQty" @input="onCryptoInput()" placeholder="0.00000000" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2 text-white font-mono text-sm focus:ring-amber-500">
                                    <span class="absolute right-3 top-2 text-xs font-bold text-gray-400">{{ $asset->symbol }}</span>
                                </div>
                            </div>

                            <div x-show="errorMessage" class="p-3 bg-rose-500/20 border border-rose-500/40 rounded-xl text-xs text-rose-300" x-text="errorMessage"></div>

                            @auth
                                <button type="button" @click="requestQuote()" :disabled="loadingQuote" class="w-full py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold rounded-xl text-sm transition duration-200 shadow-lg shadow-amber-500/20 disabled:opacity-50">
                                    <span x-show="!loadingQuote">Minta Kuotasi (15s)</span>
                                    <span x-show="loadingQuote">Memproses...</span>
                                </button>
                            @else
                                <a href="{{ route('login') }}" class="block text-center w-full py-3 bg-amber-500 hover:bg-amber-600 text-black font-bold rounded-xl text-sm transition">
                                    Masuk untuk Bertransaksi
                                </a>
                            @endauth
                        </div>

                        <!-- Active Quote & PIN Confirmation (Step 2) -->
                        <div x-show="activeQuote && !tradeSuccess" class="space-y-4">
                            <!-- Countdown Bar -->
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-amber-400 font-semibold">Kuotasi Terkunci: <span x-text="countdown" class="font-mono font-bold">15</span> detik</span>
                                </div>
                                <div class="w-full bg-gray-800 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-amber-400 h-1.5 transition-all duration-1000 ease-linear" :style="`width: ${(countdown / 15) * 100}%`"></div>
                                </div>
                            </div>

                            <!-- Quote details -->
                            <div class="bg-gray-800/60 rounded-xl p-3 border border-gray-700 text-xs space-y-1.5 font-mono">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Jumlah:</span>
                                    <span class="text-emerald-400 font-bold" x-text="`${activeQuote?.quantity} {{ $asset->symbol }}`"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Harga:</span>
                                    <span class="text-white" x-text="`Rp ${activeQuote?.price_formatted}`"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Fee (0.2%):</span>
                                    <span class="text-amber-400" x-text="`Rp ${activeQuote?.fee_formatted}`"></span>
                                </div>
                                <div class="border-t border-gray-700 pt-1.5 flex justify-between font-bold text-sm">
                                    <span class="text-white">Total IDR:</span>
                                    <span class="text-amber-400" x-text="`Rp ${formatIdr(activeQuote?.total_idr)}`"></span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">PIN Dompet (6 Digit)</label>
                                <input type="password" maxlength="6" x-model="tradePin" placeholder="••••••" class="w-full text-center tracking-[0.8em] bg-gray-800 border border-gray-700 rounded-xl py-2 text-white font-mono text-sm focus:ring-amber-500">
                            </div>

                            <div x-show="errorMessage" class="p-3 bg-rose-500/20 border border-rose-500/40 rounded-xl text-xs text-rose-300" x-text="errorMessage"></div>

                            <div class="flex gap-2">
                                <button type="button" @click="cancelQuote()" class="w-1/3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl text-xs font-semibold transition">
                                    Batal
                                </button>
                                <button type="button" @click="executeTrade()" :disabled="executingTrade || countdown <= 0 || tradePin.length !== 6" class="w-2/3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition disabled:opacity-50">
                                    <span x-show="!executingTrade">Konfirmasi</span>
                                    <span x-show="executingTrade">Memproses...</span>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Success Notification -->
                        <div x-show="tradeSuccess" class="text-center py-4 space-y-3">
                            <div class="w-12 h-12 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <h4 class="text-base font-bold text-white">Transaksi Berhasil!</h4>
                            <p class="text-xs text-gray-400" x-text="successMessage"></p>
                            <button type="button" @click="resetForm()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl text-xs font-semibold transition">
                                Transaksi Baru
                            </button>
                        </div>

                    </div>
                </div>

            </div>

        </div>
    </div>

    <script>
        function cryptoCoinView(symbol, initialChartData) {
            return {
                symbol: symbol,
                range: '24h',
                chart: null,
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
                errorMessage: '',

                init() {
                    this.renderChart(initialChartData);
                },

                renderChart(data) {
                    const ctx = document.getElementById('cryptoPriceChart').getContext('2d');
                    if (this.chart) {
                        this.chart.destroy();
                    }

                    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, 'rgba(245, 158, 11, 0.4)');
                    gradient.addColorStop(1, 'rgba(245, 158, 11, 0.0)');

                    this.chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: `${this.symbol} (IDR)`,
                                data: data.prices,
                                borderColor: '#F59E0B',
                                borderWidth: 2.5,
                                backgroundColor: gradient,
                                fill: true,
                                tension: 0.3,
                                pointRadius: data.prices.length > 30 ? 0 : 3,
                                pointHoverRadius: 6,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return 'Rp ' + Number(context.raw).toLocaleString('id-ID');
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                    ticks: { color: '#9CA3AF', maxTicksLimit: 8 }
                                },
                                y: {
                                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                    ticks: {
                                        color: '#9CA3AF',
                                        callback: function(value) {
                                            return 'Rp ' + (value >= 1000000 ? (value / 1000000).toFixed(1) + 'Jt' : value.toLocaleString('id-ID'));
                                        }
                                    }
                                }
                            }
                        }
                    });
                },

                async changeRange(newRange) {
                    this.range = newRange;
                    try {
                        const res = await fetch(`{{ url('/crypto/api/chart') }}/${this.symbol}?range=${newRange}`);
                        const data = await res.json();
                        this.renderChart(data);
                    } catch (e) {
                        console.error('Failed to load chart data:', e);
                    }
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

                formatIdr(val) {
                    if (!val) return '0';
                    return Math.round(Number(val)).toLocaleString('id-ID');
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
                                symbol: this.symbol,
                                side: this.tradeSide,
                                amount_idr: this.amountIdr || null,
                                crypto_qty: this.cryptoQty || null
                            })
                        });

                        const data = await res.json();
                        if (!res.ok || !data.success) {
                            this.errorMessage = data.message || 'Gagal memperoleh kuotasi.';
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
                            this.errorMessage = 'Kuotasi kedaluwarsa.';
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

                resetForm() {
                    this.tradeSuccess = false;
                    this.activeQuote = null;
                    this.amountIdr = '';
                    this.cryptoQty = '';
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
                            return;
                        }

                        clearInterval(this.timerInterval);
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
