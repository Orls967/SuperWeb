<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">
            {{ __('Control Tower — Sari Ranah Express') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- KPI Cards Row --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- OTIF Rate --}}
                <div class="p-5 bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-slate-400">OTIF Rate</span>
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="text-3xl font-bold {{ $otifRate >= 90 ? 'text-emerald-400' : ($otifRate >= 75 ? 'text-amber-400' : 'text-rose-400') }}">
                        {{ $otifRate }}%
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ number_format($totalDelivered) }} / {{ number_format($totalShipments) }} shipments</p>
                </div>

                {{-- Total Shipments --}}
                <div class="p-5 bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-slate-400">Total Shipments</span>
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <div class="text-3xl font-bold text-blue-400">{{ number_format($totalShipments) }}</div>
                    <p class="text-xs text-slate-500 mt-1">Periode {{ $period }}</p>
                </div>

                {{-- Active Exceptions --}}
                <div class="p-5 bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-slate-400">Exception Aktif</span>
                        <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div class="text-3xl font-bold {{ $activeExceptions > 0 ? 'text-rose-400' : 'text-emerald-400' }}">
                        {{ number_format($activeExceptions) }}
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Perlu ditangani segera</p>
                </div>

                {{-- Avg Dwell Time --}}
                <div class="p-5 bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-slate-400">Dwell Time (avg)</span>
                        <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="text-3xl font-bold text-cyan-400">{{ number_format((float) $avgDwell, 1) }}h</div>
                    <p class="text-xs text-slate-500 mt-1">Rata-rata waktu tinggal di hub</p>
                </div>
            </div>

            {{-- Status Distribution + Fleet Summary --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Status Chart --}}
                <div class="p-6 bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl">
                    <h3 class="text-lg font-bold text-slate-100 mb-4">Distribusi Status Shipment</h3>
                    <div class="space-y-3">
                        @forelse($statusChart as $status => $count)
                            @php
                                $pct = $totalShipments > 0 ? round(($count / $totalShipments) * 100, 1) : 0;
                                $colors = [
                                    'delivered' => 'bg-emerald-500',
                                    'in_transit' => 'bg-cyan-500',
                                    'booked' => 'bg-blue-500',
                                    'at_hub' => 'bg-indigo-500',
                                    'out_for_delivery' => 'bg-amber-500',
                                    'cancelled' => 'bg-gray-500',
                                    'exception' => 'bg-rose-500',
                                ];
                                $barColor = $colors[$status] ?? 'bg-slate-500';
                            @endphp
                            <div>
                                <div class="flex items-center justify-between text-sm mb-1">
                                    <span class="text-slate-300 capitalize">{{ str_replace('_', ' ', $status) }}</span>
                                    <span class="text-slate-400">{{ number_format($count) }} ({{ $pct }}%)</span>
                                </div>
                                <div class="w-full bg-slate-700/50 rounded-full h-2">
                                    <div class="{{ $barColor }} h-2 rounded-full transition-all duration-500" style="width: {{ min($pct, 100) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-slate-500 text-sm">Belum ada data.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Fleet Summary --}}
                <div class="p-6 bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl">
                    <h3 class="text-lg font-bold text-slate-100 mb-4">Status Armada Truk</h3>
                    <div class="grid grid-cols-2 gap-4">
                        @php
                            $fleetColors = [
                                'available' => ['text-emerald-400', 'bg-emerald-500/20', 'border-emerald-500/30'],
                                'assigned' => ['text-blue-400', 'bg-blue-500/20', 'border-blue-500/30'],
                                'in_transit' => ['text-cyan-400', 'bg-cyan-500/20', 'border-cyan-500/30'],
                                'maintenance' => ['text-amber-400', 'bg-amber-500/20', 'border-amber-500/30'],
                                'out_of_service' => ['text-rose-400', 'bg-rose-500/20', 'border-rose-500/30'],
                            ];
                        @endphp
                        @forelse($fleetSummary as $status => $count)
                            @php $c = $fleetColors[$status] ?? ['text-slate-400', 'bg-slate-500/20', 'border-slate-500/30']; @endphp
                            <div class="p-4 {{ $c[1] }} border {{ $c[2] }} rounded-xl text-center">
                                <div class="text-2xl font-bold {{ $c[0] }}">{{ $count }}</div>
                                <div class="text-xs text-slate-400 capitalize mt-1">{{ str_replace('_', ' ', $status) }}</div>
                            </div>
                        @empty
                            <p class="col-span-2 text-slate-500 text-sm">Belum ada data armada.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- COD + Top Lanes --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- COD Dashboard --}}
                <div class="p-6 bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl">
                    <h3 class="text-lg font-bold text-slate-100 mb-4">Cash on Delivery (COD)</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-xl">
                            <div class="text-sm text-amber-300 mb-1">Outstanding</div>
                            <div class="text-xl font-bold text-amber-400">Rp {{ number_format((int) $codOutstanding) }}</div>
                        </div>
                        <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-xl">
                            <div class="text-sm text-emerald-300 mb-1">Settled</div>
                            <div class="text-xl font-bold text-emerald-400">Rp {{ number_format((int) $codSettled) }}</div>
                        </div>
                    </div>
                </div>

                {{-- Top Lanes by Revenue --}}
                <div class="p-6 bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl">
                    <h3 class="text-lg font-bold text-slate-100 mb-4">Top Lanes (Revenue)</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-slate-400 border-b border-slate-700">
                                    <th class="text-left py-2 px-2">Lane</th>
                                    <th class="text-right py-2 px-2">Shipments</th>
                                    <th class="text-right py-2 px-2">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topLanes as $lane)
                                    <tr class="border-b border-slate-800/50 hover:bg-slate-700/20 transition-colors">
                                        <td class="py-2 px-2 text-slate-300 text-xs">{{ $lane->lane }}</td>
                                        <td class="py-2 px-2 text-right text-slate-400">{{ number_format($lane->shipments) }}</td>
                                        <td class="py-2 px-2 text-right text-emerald-400 font-medium">Rp {{ number_format((int) $lane->revenue) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-4 text-center text-slate-500">Belum ada data.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
