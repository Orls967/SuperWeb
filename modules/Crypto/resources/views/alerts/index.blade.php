<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold bg-gradient-to-r from-amber-400 via-orange-400 to-yellow-500 bg-clip-text text-transparent flex items-center gap-2">
                    <svg class="w-7 h-7 text-amber-400 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    Price Alerts (Pengingat Harga)
                </h2>
                <p class="text-sm text-gray-400 mt-1">Dapatkan pembaruan otomatis ketika aset mencapai target harga beli atau jual Anda.</p>
            </div>
            <div>
                <a href="{{ route('crypto.market.index') }}" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl text-sm font-semibold flex items-center gap-2 transition duration-200">
                    &larr; Kembali ke Pasar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl text-emerald-400 text-sm font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Form Buat Alert -->
                <div class="bg-gray-900/80 border border-gray-800/80 rounded-3xl p-6 backdrop-blur-xl space-y-4">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Buat Pengingat Baru
                    </h3>
                    <form action="{{ route('crypto.alerts.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Pilih Koin</label>
                            <select name="asset_id" required class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl text-sm py-2.5 px-3 focus:ring-amber-500 font-mono">
                                @foreach($assets as $asset)
                                    <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->symbol }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Kondisi</label>
                            <select name="condition" required class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl text-sm py-2.5 px-3 focus:ring-amber-500">
                                <option value="above">Harga Naik Di Atas (>=)</option>
                                <option value="below">Harga Turun Di Bawah (<=)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-1">Target Harga (IDR)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs font-bold text-gray-500">Rp</span>
                                <input type="number" name="target_price_idr" required placeholder="0" class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl pl-10 pr-4 py-2.5 text-sm font-mono focus:ring-amber-500">
                            </div>
                        </div>

                        <button type="submit" class="w-full py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold rounded-xl text-sm transition duration-200 shadow-lg shadow-amber-500/20">
                            Pasang Alert
                        </button>
                    </form>
                </div>

                <!-- Daftar Alerts -->
                <div class="lg:col-span-2 bg-gray-900/80 border border-gray-800/80 rounded-3xl p-6 backdrop-blur-xl space-y-4">
                    <h3 class="text-base font-bold text-white">Daftar Pengingat Anda</h3>

                    @if($alerts->isEmpty())
                        <div class="text-center py-12 text-gray-500 text-sm font-mono">
                            Belum ada price alert yang dipasang.
                        </div>
                    @else
                        <div class="divide-y divide-gray-800/60 font-mono text-xs">
                            @foreach ($alerts as $alert)
                                <div class="py-3 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-gray-800 border border-gray-700 font-bold text-amber-400 flex items-center justify-center text-xs">
                                            {{ $alert->asset->symbol }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-white font-sans text-sm">{{ $alert->asset->name }}</div>
                                            <div class="text-gray-400 text-xs">
                                                {{ $alert->condition->label() }}: <span class="text-amber-400 font-bold">Rp {{ number_format((float)$alert->target_price_idr, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        @if($alert->is_triggered)
                                            <span class="px-2.5 py-1 rounded-full text-2xs font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                                Tercapai ({{ $alert->triggered_at?->format('d M H:i') }})
                                            </span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-full text-2xs font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                                                Menunggu
                                            </span>
                                        @endif
                                        <form action="{{ route('crypto.alerts.destroy', $alert) }}" method="POST" onsubmit="return confirm('Hapus alert ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-gray-500 hover:text-rose-400 rounded-lg hover:bg-gray-800 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4">
                            {{ $alerts->links() }}
                        </div>
                    @endif
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
