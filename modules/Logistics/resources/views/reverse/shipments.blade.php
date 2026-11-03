<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight flex items-center gap-2">
                <span class="text-emerald-400">🔄</span> {{ __('Reverse Logistics Shipments & Plant Intake') }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('logistics.circular-ledger.index') }}" class="px-3 py-1 text-xs font-semibold rounded-lg bg-teal-500/20 text-teal-300 border border-teal-500/30 hover:bg-teal-500/30 transition">
                    📊 Buku Besar Sirkular
                </a>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                    Fase 79 — KONSEP §5F Slice 1
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-950 min-h-screen text-slate-100">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 bg-emerald-900/50 border border-emerald-500/50 rounded-xl text-emerald-200 text-sm flex items-center gap-2 shadow-lg">
                    <span>✓</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl">
                    <div class="text-[11px] uppercase tracking-wider text-slate-400 font-medium">Total Reverse</div>
                    <div class="text-2xl font-bold text-slate-100 mt-1">{{ number_format($stats['total_reverse']) }}</div>
                </div>
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl">
                    <div class="text-[11px] uppercase tracking-wider text-amber-400 font-medium">Diminta</div>
                    <div class="text-2xl font-bold text-amber-300 mt-1">{{ number_format($stats['requested']) }}</div>
                </div>
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl">
                    <div class="text-[11px] uppercase tracking-wider text-blue-400 font-medium">Di Hub</div>
                    <div class="text-2xl font-bold text-blue-300 mt-1">{{ number_format($stats['at_hub']) }}</div>
                </div>
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl">
                    <div class="text-[11px] uppercase tracking-wider text-cyan-400 font-medium">Tiba di Pabrik</div>
                    <div class="text-2xl font-bold text-cyan-300 mt-1">{{ number_format($stats['delivered_to_plant']) }}</div>
                </div>
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl">
                    <div class="text-[11px] uppercase tracking-wider text-emerald-400 font-medium">Diterima Pabrik</div>
                    <div class="text-2xl font-bold text-emerald-300 mt-1">{{ number_format($stats['received']) }}</div>
                </div>
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl">
                    <div class="text-[11px] uppercase tracking-wider text-rose-400 font-medium">Ditolak</div>
                    <div class="text-2xl font-bold text-rose-300 mt-1">{{ number_format($stats['rejected']) }}</div>
                </div>
            </div>

            <!-- Shipments Table -->
            <div class="p-6 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-200 flex items-center gap-2">
                        <span>🚚</span> Daftar Pengiriman Logistik Balik
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-800/60 text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Tracking / Manifest</th>
                                <th class="px-4 py-3">Tipe & Material</th>
                                <th class="px-4 py-3">Grade & Berat</th>
                                <th class="px-4 py-3">Pabrik Tujuan</th>
                                <th class="px-4 py-3">Rantai Kustodi Hash</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @forelse($shipments as $sh)
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="px-4 py-3 font-mono text-xs">
                                        <div class="font-semibold text-slate-100">{{ $sh->tracking_number }}</div>
                                        <div class="text-slate-500 text-[11px]">{{ $sh->reverse_manifest_number }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">{{ $sh->reverse_type }}</span>
                                        <div class="text-slate-400 mt-1">{{ $sh->material_code }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <span class="font-semibold text-emerald-400">Grade {{ $sh->quality_grade }}</span>
                                        <div class="text-slate-400">{{ number_format($sh->weight_grams) }} g ({{ round($sh->weight_grams / 1000, 2) }} kg)</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <div class="font-semibold text-slate-200">{{ $sh->plant_id }}</div>
                                        @if($sh->plant_receipt_id)
                                            <div class="text-emerald-400 text-[11px]">Receipt #{{ $sh->plant_receipt_id }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono">
                                        <div class="text-slate-500 truncate max-w-[120px]" title="{{ $sh->reverse_chain_hash }}">
                                            {{ substr($sh->reverse_chain_hash ?? '—', 0, 16) }}...
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-medium
                                            @if($sh->status === 'received') bg-emerald-500/20 text-emerald-400 border border-emerald-500/30
                                            @elseif($sh->status === 'rejected') bg-rose-500/20 text-rose-400 border border-rose-500/30
                                            @elseif($sh->status === 'delivered_to_plant') bg-cyan-500/20 text-cyan-400 border border-cyan-500/30
                                            @elseif($sh->status === 'at_hub') bg-blue-500/20 text-blue-400 border border-blue-500/30
                                            @else bg-amber-500/20 text-amber-400 border border-amber-500/30 @endif">
                                            {{ $sh->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($sh->status === 'requested')
                                            <form action="{{ route('logistics.reverse.shipments.transition', $sh->id) }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="target_status" value="picked_up">
                                                <button type="submit" class="px-2.5 py-1 bg-amber-600 hover:bg-amber-500 text-white rounded text-xs">Jemput</button>
                                            </form>
                                        @elseif($sh->status === 'picked_up')
                                            <form action="{{ route('logistics.reverse.shipments.transition', $sh->id) }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="target_status" value="at_hub">
                                                <button type="submit" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-500 text-white rounded text-xs">Tiba di Hub</button>
                                            </form>
                                        @elseif($sh->status === 'at_hub')
                                            <form action="{{ route('logistics.reverse.shipments.transition', $sh->id) }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="target_status" value="delivered_to_plant">
                                                <button type="submit" class="px-2.5 py-1 bg-cyan-600 hover:bg-cyan-500 text-white rounded text-xs">Kirim ke Pabrik</button>
                                            </form>
                                        @else
                                            <span class="text-slate-500 text-xs">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-slate-500 text-sm">
                                        Belum ada shipment reverse logistics.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $shipments->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

