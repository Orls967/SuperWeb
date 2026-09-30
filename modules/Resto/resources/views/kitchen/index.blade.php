<x-app-layout>
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6"
         x-data="kitchenBoard({
             outletId: {{ $currentOutlet->id }},
             simulateUrl: '{{ route('resto.kitchen.simulate') }}'
         })">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-900/60 backdrop-blur-md p-6 rounded-2xl border border-slate-800 shadow-xl">
            <div class="flex items-center gap-4">
                <span class="p-3 rounded-2xl bg-amber-500/10 text-amber-400 border border-amber-500/20 shadow-inner">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"></path></svg>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold text-white tracking-tight">Dapur & Etalase Hidang</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            {{ $currentOutlet->code }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-400">Papan produksi batch, siklus etalase Padang, kontrol resirkulasi piring, dan manajemen waste</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                @if(auth()->user()->isAdmin() || auth()->user()->isOutletManager())
                    <form method="GET" action="{{ route('resto.kitchen.index') }}" class="flex items-center">
                        <select name="outlet_id" onchange="this.form.submit()" class="bg-slate-800 border border-slate-700 text-sm text-white rounded-xl px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                            @foreach($outlets as $out)
                                <option value="{{ $out->id }}" {{ $currentOutlet->id === $out->id ? 'selected' : '' }}>
                                    {{ $out->name }} ({{ $out->code }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif

                <button type="button" @click="openCookModal()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-medium shadow-lg shadow-amber-500/25 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Masak Batch Baru
                </button>
            </div>
        </div>

        <!-- Flash Notifications -->
        @if(session('success'))
            <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-emerald-400 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 bg-rose-500/10 border border-rose-500/20 rounded-xl text-rose-400 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Quick Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900/60 backdrop-blur-md p-5 rounded-2xl border border-slate-800">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-xs uppercase font-medium">Batch Hari Ini</span>
                    <span class="p-1.5 bg-blue-500/10 text-blue-400 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </span>
                </div>
                <div class="text-2xl font-bold text-white">{{ $todayBatches->count() }}</div>
                <div class="text-xs text-slate-400 mt-1">
                    Total: {{ $todayBatches->sum('actual_portions') }} porsi matang
                </div>
            </div>

            <div class="bg-slate-900/60 backdrop-blur-md p-5 rounded-2xl border border-slate-800">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-xs uppercase font-medium">Piring di Etalase</span>
                    <span class="p-1.5 bg-amber-500/10 text-amber-400 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                    </span>
                </div>
                <div class="text-2xl font-bold text-amber-400">{{ $displayTrays->count() }}</div>
                <div class="text-xs text-slate-400 mt-1">
                    {{ $displayTrays->sum('portions_remaining') }} porsi siap hidang
                </div>
            </div>

            <div class="bg-slate-900/60 backdrop-blur-md p-5 rounded-2xl border border-slate-800">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-xs uppercase font-medium">Limbah / Waste Hari Ini</span>
                    <span class="p-1.5 bg-rose-500/10 text-rose-400 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </span>
                </div>
                <div class="text-2xl font-bold text-rose-400">Rp {{ number_format($todayWasteTotal, 0, ',', '.') }}</div>
                <div class="text-xs text-slate-400 mt-1">
                    {{ $todayWastes->count() }} piring dialihkan
                </div>
            </div>

            <div class="bg-slate-900/60 backdrop-blur-md p-5 rounded-2xl border border-slate-800">
                <div class="flex items-center justify-between text-slate-400 mb-2">
                    <span class="text-xs uppercase font-medium">Batas Waktu Etalase</span>
                    <span class="p-1.5 bg-emerald-500/10 text-emerald-400 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                </div>
                <div class="text-2xl font-bold text-emerald-400">Maks. 6 Jam</div>
                <div class="text-xs text-slate-400 mt-1">
                    Maks. 3x resirkulasi hidang
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 border-b border-slate-800 pb-2">
            <button type="button" @click="activeTab = 'display'" :class="activeTab === 'display' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'text-slate-400 hover:text-white border-transparent'" class="px-4 py-2 rounded-xl text-sm font-semibold border transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                Monitor Etalase Hidang ({{ $displayTrays->count() }})
            </button>
            <button type="button" @click="activeTab = 'production'" :class="activeTab === 'production' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'text-slate-400 hover:text-white border-transparent'" class="px-4 py-2 rounded-xl text-sm font-semibold border transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                Papan Produksi Hari Ini ({{ $todayBatches->count() }})
            </button>
            <button type="button" @click="activeTab = 'waste'" :class="activeTab === 'waste' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'text-slate-400 hover:text-white border-transparent'" class="px-4 py-2 rounded-xl text-sm font-semibold border transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                Rekap Waste ({{ $todayWastes->count() }})
            </button>
        </div>

        <!-- 1. Monitor Etalase Hidang -->
        <div x-show="activeTab === 'display'" class="space-y-4">
            @if($displayTrays->isEmpty())
                <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-12 text-center">
                    <span class="inline-block p-4 rounded-full bg-slate-800/80 text-slate-500 mb-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    </span>
                    <h3 class="text-lg font-bold text-white mb-1">Etalase Masih Kosong</h3>
                    <p class="text-sm text-slate-400 mb-4">Belum ada piring masakan yang sedang dipajang di etalase outlet ini.</p>
                    <button type="button" @click="openCookModal()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium transition shadow-lg shadow-amber-500/25">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Masak & Pajang Batch Pertama
                    </button>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($displayTrays as $tray)
                        @php
                            $minutesRemaining = now()->diffInMinutes($tray->expires_at, false);
                            $isCritical = $minutesRemaining <= 60;
                            $isWarning = $minutesRemaining > 60 && $minutesRemaining <= 120;
                        @endphp
                        <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border {{ $isCritical ? 'border-rose-500/50 ring-1 ring-rose-500/20 bg-rose-950/10' : ($isWarning ? 'border-amber-500/50 ring-1 ring-amber-500/20' : 'border-slate-800') }} p-5 flex flex-col justify-between shadow-xl transition hover:border-slate-700">
                            <div>
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div>
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-slate-800 text-slate-400 uppercase tracking-wider">
                                            {{ $tray->menuItem->category?->name ?? 'Lauk Padang' }}
                                        </span>
                                        <h3 class="text-lg font-bold text-white mt-1">{{ $tray->menuItem->name }}</h3>
                                        <p class="text-xs text-slate-400">Batch: {{ $tray->batch?->batch_no ?? '-' }}</p>
                                    </div>

                                    <div class="text-right">
                                        <span class="inline-block px-2 py-1 rounded-lg text-xs font-bold {{ $tray->recirculation_count >= 2 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-800 text-slate-400' }}">
                                            Resirkulasi: {{ $tray->recirculation_count }}/{{ \Modules\Resto\Domain\Models\DisplayTray::MAX_RECIRCULATION }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Portions & Value -->
                                <div class="grid grid-cols-2 gap-2 my-4 p-3 bg-slate-950/60 rounded-xl border border-slate-800/80">
                                    <div>
                                        <span class="text-xs text-slate-500 block">Sisa Porsi</span>
                                        <span class="text-xl font-bold text-amber-400">{{ $tray->portions_remaining }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-slate-500 block">HPP / Porsi</span>
                                        <span class="text-sm font-semibold text-slate-300">Rp {{ number_format($tray->cost_per_portion, 0, ',', '.') }}</span>
                                    </div>
                                </div>

                                <!-- Expiry Countdown Badge -->
                                <div class="mb-4">
                                    @if($minutesRemaining <= 0)
                                        <div class="p-2.5 rounded-xl bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs font-bold flex items-center justify-between animate-pulse">
                                            <span>KEDALUWARSA (> 6 JAM)</span>
                                            <span>Wajib Dibuang</span>
                                        </div>
                                    @elseif($isCritical)
                                        <div class="p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold flex items-center justify-between">
                                            <span class="flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                                                Kritis: {{ $minutesRemaining }} menit tersisa
                                            </span>
                                            <span>Selesai: {{ $tray->expires_at->format('H:i') }}</span>
                                        </div>
                                    @elseif($isWarning)
                                        <div class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-semibold flex items-center justify-between">
                                            <span>Perhatian: {{ floor($minutesRemaining / 60) }} jam {{ $minutesRemaining % 60 }} mnt</span>
                                            <span>Selesai: {{ $tray->expires_at->format('H:i') }}</span>
                                        </div>
                                    @else
                                        <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold flex items-center justify-between">
                                            <span>Segar: {{ floor($minutesRemaining / 60) }} jam {{ $minutesRemaining % 60 }} mnt</span>
                                            <span>Selesai: {{ $tray->expires_at->format('H:i') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="pt-3 border-t border-slate-800 flex items-center justify-between gap-2">
                                @if($tray->status === \Modules\Resto\Domain\Enums\TrayStatus::IN_SERVICE || $tray->status === \Modules\Resto\Domain\Enums\TrayStatus::RETURNED)
                                    <form method="POST" action="{{ route('resto.kitchen.trays.recirculate', $tray) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/30 text-xs font-semibold transition">
                                            Kembali ke Etalase
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-500 font-medium flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        Aktif di Etalase
                                    </span>
                                @endif

                                <button type="button" @click="openDiscardModal({{ $tray->id }}, '{{ addslashes($tray->menuItem->name) }}', {{ $tray->portions_remaining }})" class="px-3 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-semibold transition">
                                    Buang / Waste
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- 2. Papan Produksi Hari Ini -->
        <div x-show="activeTab === 'production'" class="space-y-4" style="display: none;">
            <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 overflow-hidden shadow-xl">
                <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                    <h2 class="text-base font-bold text-white">Catatan Masak & Batch Produksi (Hari Ini)</h2>
                    <span class="text-xs text-slate-400">Total {{ $todayBatches->count() }} batch</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="px-4 py-3">No. Batch</th>
                                <th class="px-4 py-3">Menu / Resep</th>
                                <th class="px-4 py-3 text-center">Rencana</th>
                                <th class="px-4 py-3 text-center">Aktual</th>
                                <th class="px-4 py-3 text-center">Varians</th>
                                <th class="px-4 py-3">Total HPP</th>
                                <th class="px-4 py-3">Koki</th>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @forelse($todayBatches as $batch)
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-4 py-3 font-mono text-xs text-amber-400 font-semibold">{{ $batch->batch_no }}</td>
                                    <td class="px-4 py-3 font-semibold text-white">
                                        {{ $batch->menuItem?->name ?? $batch->recipe?->sub_recipe_name ?? 'Resep' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">{{ $batch->planned_portions }}</td>
                                    <td class="px-4 py-3 text-center font-bold text-white">{{ $batch->actual_portions }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($batch->variancePortions() == 0)
                                            <span class="text-xs text-slate-400">0</span>
                                        @elseif($batch->variancePortions() > 0)
                                            <span class="text-xs text-emerald-400 font-semibold">+{{ $batch->variancePortions() }}</span>
                                        @else
                                            <span class="text-xs text-rose-400 font-semibold">{{ $batch->variancePortions() }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-200">Rp {{ number_format($batch->cost_total, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-400">{{ $batch->producedBy?->name ?? 'Dapur' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-400">{{ $batch->created_at->format('H:i') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-800 text-slate-300">
                                            {{ $batch->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-slate-500">
                                        Belum ada batch yang dimasak hari ini di outlet ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. Rekap Waste Hari Ini -->
        <div x-show="activeTab === 'waste'" class="space-y-4" style="display: none;">
            <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 overflow-hidden shadow-xl">
                <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-white">Rekap Limbah Makanan (Waste) Hari Ini</h2>
                        <p class="text-xs text-slate-400">Piring etalase kedaluwarsa atau rusak yang diposting ke ledger expense:resto:waste:IDR</p>
                    </div>
                    <span class="text-sm font-bold text-rose-400">
                        Total Kerugian: Rp {{ number_format($todayWasteTotal, 0, ',', '.') }}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="px-4 py-3">ID Piring</th>
                                <th class="px-4 py-3">Menu</th>
                                <th class="px-4 py-3 text-center">Porsi Dibuang</th>
                                <th class="px-4 py-3">HPP / Porsi</th>
                                <th class="px-4 py-3">Total Nilai Waste</th>
                                <th class="px-4 py-3">Waktu Dibuang</th>
                                <th class="px-4 py-3">Resirkulasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @forelse($todayWastes as $w)
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-4 py-3 font-mono text-xs text-slate-400">#{{ $w->id }}</td>
                                    <td class="px-4 py-3 font-semibold text-white">{{ $w->menuItem->name }}</td>
                                    <td class="px-4 py-3 text-center font-bold text-rose-400">{{ $w->portions_remaining ?: 1 }}</td>
                                    <td class="px-4 py-3">Rp {{ number_format($w->cost_per_portion, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 font-bold text-rose-400">
                                        Rp {{ number_format((int) $w->cost_per_portion * (int) ($w->portions_remaining ?: 1), 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-400">{{ $w->updated_at->format('H:i') }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-400">{{ $w->recirculation_count }} kali</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                        Tidak ada limbah/waste tercatat hari ini. Dapur bersih dan efisien!
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL: Masak Batch Baru -->
        <div x-show="isCookingModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
            <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" @click="closeCookModal()"></div>

            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl space-y-5" @click.stop>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            </span>
                            <h3 class="text-lg font-bold text-white">Form Masak Batch Dapur</h3>
                        </div>
                        <button type="button" @click="closeCookModal()" class="text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('resto.kitchen.cook') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="outlet_id" value="{{ $currentOutlet->id }}">

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">Pilih Resep / Menu</label>
                            <select name="recipe_id" x-model="selectedRecipeId" @change="simulate()" required class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-4 py-2.5 text-sm focus:ring-amber-500 focus:border-amber-500">
                                <option value="">-- Pilih Resep Menu Padang --</option>
                                @foreach($recipes as $rec)
                                    <option value="{{ $rec->id }}">
                                        {{ $rec->menuItem ? $rec->menuItem->name : $rec->sub_recipe_name }} (Standar: {{ $rec->expected_portions }} porsi)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">Porsi Rencana</label>
                                <input type="number" name="planned_portions" x-model.number="plannedPortions" @input.debounce.300ms="onPortionsChange()" min="1" required class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-4 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">Porsi Aktual Hasil Masak</label>
                                <input type="number" name="actual_portions" x-model.number="actualPortions" min="1" required class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-4 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            </div>
                        </div>

                        <!-- Live Simulation Section -->
                        <div class="p-4 bg-slate-950/70 border border-slate-800/80 rounded-2xl space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                    Simulasi Ketersediaan Bahan Baku
                                </span>

                                <template x-if="isSimulating">
                                    <span class="text-xs text-amber-400 animate-pulse">Menghitung...</span>
                                </template>
                            </div>

                            <template x-if="!selectedRecipeId">
                                <p class="text-xs text-slate-500 italic">Pilih resep di atas untuk melihat simulasi bahan.</p>
                            </template>

                            <template x-if="simulationResult">
                                <div class="space-y-3">
                                    <!-- Shortage Alert -->
                                    <template x-if="!simulationResult.can_cook">
                                        <div class="p-3 bg-rose-500/15 border border-rose-500/30 rounded-xl text-xs space-y-2">
                                            <div class="flex items-center justify-between text-rose-300 font-bold">
                                                <span>⚠️ Bahan Kurang Untuk <span x-text="plannedPortions"></span> Porsi!</span>
                                                <template x-if="simulationResult.suggested_portions > 0">
                                                    <button type="button" @click="applySuggestedPortions()" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-semibold transition">
                                                        Sesuaikan Jadi <span x-text="simulationResult.suggested_portions"></span> Porsi
                                                    </button>
                                                </template>
                                            </div>
                                            <p class="text-slate-300">Sebagian bahan baku tidak mencukupi di outlet ini. Anda dapat mengurangi target porsi atau mengisi stok melalui PO.</p>
                                        </div>
                                    </template>

                                    <!-- Success Alert -->
                                    <template x-if="simulationResult.can_cook">
                                        <div class="p-2.5 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-xs text-emerald-400 flex items-center gap-2">
                                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            <span>Semua bahan baku tersedia cukup di outlet untuk <span class="font-bold" x-text="plannedPortions"></span> porsi.</span>
                                        </div>
                                    </template>

                                    <!-- Ingredients breakdown list -->
                                    <div class="max-h-48 overflow-y-auto space-y-1.5 pr-1">
                                        <template x-for="item in simulationResult.ingredients" :key="item.ingredient_id">
                                            <div class="flex items-center justify-between p-2 rounded-lg text-xs" :class="item.is_shortage ? 'bg-rose-950/40 border border-rose-800/40 text-rose-300' : 'bg-slate-900/80 text-slate-300'">
                                                <div>
                                                    <span class="font-medium text-white" x-text="item.name"></span>
                                                    <span class="text-slate-400 ml-1">(<span x-text="item.needed"></span> <span x-text="item.base_unit"></span>)</span>
                                                </div>
                                                <div class="text-right">
                                                    <span class="text-slate-400">Stok: </span>
                                                    <span :class="item.is_shortage ? 'text-rose-400 font-bold' : 'text-emerald-400 font-semibold'" x-text="item.available + ' ' + item.base_unit"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <input type="checkbox" id="put_on_display" name="put_on_display" value="1" x-model="putOnDisplay" class="rounded bg-slate-800 border-slate-700 text-amber-500 focus:ring-amber-500">
                            <label for="put_on_display" class="text-xs text-slate-300">Langsung tempatkan ke piring etalase hidang setelah matang</label>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">Catatan Produksi (Opsional)</label>
                            <input type="text" name="note" x-model="cookingNote" placeholder="Misal: tingkat kepedasan cabai tinggi" class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-4 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                            <button type="button" @click="closeCookModal()" class="px-4 py-2 text-sm text-slate-400 hover:text-white font-medium transition">
                                Batal
                            </button>
                            <button type="submit" :disabled="!selectedRecipeId || (simulationResult && !simulationResult.can_cook)" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white text-sm font-semibold shadow-lg shadow-amber-500/25 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                Konfirmasi & Masak Batch
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: Buang Piring ke Waste -->
        <div x-show="isDiscardModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
            <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" @click="closeDiscardModal()"></div>

            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl space-y-4" @click.stop>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <div class="flex items-center gap-2 text-rose-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            <h3 class="text-lg font-bold text-white">Buang Piring ke Waste</h3>
                        </div>
                        <button type="button" @click="closeDiscardModal()" class="text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <form :action="'/resto/kitchen/trays/' + discardTrayId + '/discard'" method="POST" class="space-y-4">
                        @csrf
                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs space-y-1">
                            <div class="text-slate-400">Piring: <span class="font-bold text-white" x-text="discardTrayName"></span></div>
                            <div class="text-slate-400">Sisa Porsi Dibuang: <span class="font-bold text-amber-400" x-text="discardPortions"></span> porsi</div>
                            <div class="text-slate-500 text-[11px] mt-1">*Nilai HPP porsi sisa akan otomatis diposting ke ledger expense:resto:waste:IDR.</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">Alasan Pembuangan</label>
                            <input type="text" name="reason" x-model="discardReason" required class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-4 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" @click="discardReason = 'Batas waktu pajang etalase habis (6 jam)'" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] text-slate-300">
                                Waktu Habis (6 Jam)
                            </button>
                            <button type="button" @click="discardReason = 'Batas resirkulasi terlampaui (3x)'" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] text-slate-300">
                                Batas Resirkulasi (3x)
                            </button>
                            <button type="button" @click="discardReason = 'Kualitas basi / bau asam'" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] text-slate-300">
                                Basi / Bau
                            </button>
                            <button type="button" @click="discardReason = 'Piring pecah / jatuh'" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] text-slate-300">
                                Pecah / Jatuh
                            </button>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                            <button type="button" @click="closeDiscardModal()" class="px-4 py-2 text-sm text-slate-400 hover:text-white font-medium transition">
                                Batal
                            </button>
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold shadow-lg shadow-rose-600/25 transition">
                                Konfirmasi Buang
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
